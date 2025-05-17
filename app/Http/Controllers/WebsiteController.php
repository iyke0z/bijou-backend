<?php

namespace App\Http\Controllers;

use App\Models\About;
use App\Models\BusinessDetails;
use App\Models\Contact;
use App\Models\ReturnPolicy;
use App\Models\FAQ;
use App\Models\HeroBanner;
use App\Models\Item;
use App\Models\PaymentConfig;
use App\Models\Setting;
use App\Models\SocialLink;
use App\Models\Sustainability;
use App\Models\ThemeSetting;
use App\Models\User;
use Dompdf\Image\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class WebsiteController extends Controller
{
      public function createAbout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'item_title' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return DB::transaction(function () use ($request) {
                    $about = About::first();
                    if ($about) {
                        $about->update([
                            'title' => $request->title,
                            'description' => $request->description,
                        ]);
                    } else {
                        $about = About::create([
                            'title' => $request->title,
                            'description' => $request->description,
                        ]);
                    }

                    // Delete existing items and create new ones
                    Item::where('about_id', $about->id)->delete();
                    foreach ($request->items as $itemData) {
                        Item::create([
                            'title' => $request->item_title,
                            'name' => $itemData['name'],
                            'description' => $itemData['description'],
                            'about_id' => $about->id,
                        ]);
                    }

                    return res_completed('About updated successfully');
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if (strpos($e->getMessage(), 'Lock wait timeout') !== false && $attempt < $maxAttempts - 1) {
                    $attempt++;
                    sleep(1);
                    continue;
                }
                throw $e;
            }
        }

        return response()->json(['error' => 'Database lock timeout. Please try again.'], 500);
    }

    public function showAbout()
    {
            $about = About::first();
            $items = $about ? Item::where('about_id', $about->id)->get() : collect([]);
            return response()->json([
                'title' => $about ? $about->title : null,
                'story' => $about ? ['title' => $about->title, 'description' => $about->description] : null,
                'values' => $about ? [
                    'title' => $items->first() ? $items->first()->title : null,
                    'items' => $items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->name,
                            'description' => $item->description,
                        ];
                    })->toArray(),
                ] : null,
            ]);
    }

    public function deleteAboutItem($id)
    {
        return DB::transaction(function () use ($id) {
            $item = Item::findOrFail($id);
            $item->delete();
            return res_completed('Item deleted successfully');
        });
    }

    public function createHeroBanner(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif', // Only validate if image is present
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $fileName = $request->file('image')->getClientOriginalName();
        $request->file('image')->storeAs('hero_banners', $fileName, 'public');

        $heroBanner = new HeroBanner();
        $heroBanner->image = $fileName;
        $heroBanner->title = $request->title;
        $heroBanner->description = $request->description;
        $heroBanner->save();
        
        return res_completed('Hero Banner created successfully');
    }

    public function updateHeroBanner(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif', // Only validate if image is present
            'title' => 'sometimes|string|max:255', // Only validate if title is present
            'description' => 'sometimes|string|max:255', // Only validate if description is present
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $heroBanner = HeroBanner::findOrFail($id);

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete('hero_banners/' . $heroBanner->image);
            $fileName = $request->file('image')->getClientOriginalName();
            $request->file('image')->storeAs('hero_banners', $fileName, 'public');
            $heroBanner->image = $fileName;
        }

        if ($request->has('title')) {
            $heroBanner->title = $request->title;
        }

        if ($request->has('description')) {
            $heroBanner->description = $request->description;
        }

        $heroBanner->save();

        return res_completed('Hero Banner updated successfully');
    }

    public function deleteHeroBanner($id)
    {
        $heroBanner = HeroBanner::findOrFail($id);
        Storage::disk('public')->delete('hero_banners/' . $heroBanner->image);
        $heroBanner->delete();

        return res_completed('Hero Banner deleted successfully');
    }
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brand_name' => 'required|string|max:255',
            'footer_description' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            Log::warning('Settings validation failed', ['errors' => $validator->errors()]);
            return response()->json(['error' => $validator->errors()], 422);
        }

        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return DB::transaction(function () use ($request) {
                    $settings = Setting::first();
                    $data = [
                        'brand_name' => $request->brand_name,
                        'footer_description' => $request->footer_description,
                    ];
                    if ($settings) {
                        $settings->update($data);
                    } else {
                        $settings = Setting::create($data);
                    }
                    Log::info('Settings updated successfully', ['data' => $data]);
                    return response()->json(['message' => 'Settings updated successfully'], 200);
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if (strpos($e->getMessage(), 'Lock wait timeout') !== false && $attempt < $maxAttempts - 1) {
                    $attempt++;
                    Log::warning("Lock timeout on attempt {$attempt} for updateSettings");
                    sleep(1);
                    continue;
                }
                Log::error('Update Settings failed: ' . $e->getMessage(), ['request' => $request->all()]);
                return response()->json(['error' => 'Database error. Please try again.'], 500);
            }
        }

        Log::error('Update Settings exceeded max attempts due to lock timeout');
        return response()->json(['error' => 'Database lock timeout. Please try again.'], 500);
    }

    public function showSettings()
    {
        try {
                $settings = Setting::first();
                $data = [
                    'brand_name' => $settings ? $settings->brand_name : 'My Shop',
                    'footer_description' => $settings ? $settings->footer_description : 'I am a footer description',
                ];
                Log::info('Settings fetched successfully', ['data' => $data]);
                return response()->json($data, 200);
        } catch (\Exception $e) {
            Log::error('Show Settings failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch settings.'], 500);
        }
    }

    public function createSocialLinks(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'links' => 'required|array',
            'links.*.label' => 'required|string',
            // 'links.*.path' => 'required|string|max:255|url',
        ]);

        if ($validator->fails()) {
            Log::warning('Social Links validation failed', ['errors' => $validator->errors()]);
            return response()->json(['error' => $validator->errors()], 422);
        }

        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return DB::transaction(function () use ($request) {
                    $orderMap = [
                        'Instagram' => 0,
                        'Facebook' => 1,
                        'Twitter' => 2,
                        'Pinterest' => 3,
                    ];
                    $iconMap = [
                        'Instagram' => 'instagram.svg',
                        'Facebook' => 'facebook.svg',
                        'Twitter' => 'twitter.svg',
                        'Pinterest' => 'pinterest.svg',
                    ];

                    foreach ($request->links as $link) {
                        SocialLink::updateOrCreate(
                            ['label' => $link['label']],
                            [
                                'path' => $link['path'],
                                'icon' => $iconMap[$link['label']],
                                'order' => $orderMap[$link['label']],
                                'is_active' => true,
                            ]
                        );
                    }
                    Log::info('Social Links updated successfully', ['links' => $request->links]);
                    return response()->json(['message' => 'Social links updated successfully'], 200);
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if (strpos($e->getMessage(), 'Lock wait timeout') !== false && $attempt < $maxAttempts - 1) {
                    $attempt++;
                    Log::warning("Lock timeout on attempt {$attempt} for createSocialLinks");
                    sleep(1);
                    continue;
                }
                Log::error('Create Social Links failed: ' . $e->getMessage(), ['request' => $request->all()]);
                return response()->json(['error' => 'Database error. Please try again.'], 500);
            }
        }

        Log::error('Create Social Links exceeded max attempts due to lock timeout');
        return response()->json(['error' => 'Database lock timeout. Please try again.'], 500);
    }

    public function showSocialLinks()
    {
        try {
                $links = SocialLink::where('is_active', true)
                    ->orderBy('order')
                    ->get();
                Log::info('Social Links fetched successfully', ['links' => $links->toArray()]);
                return response()->json(['links' => $links], 200);
            }
         catch (\Exception $e) {
            Log::error('Show Social Links failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch social links.'], 500);
        }
    }

    public function createFAQ(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'answer' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        FAQ::create([
            'question' => $request->question,
            'answer' => $request->answer,
        ]);

        return res_completed('FAQ created successfully');
    }

    public function updateFAQ(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'sometimes|string|max:255',
            'answer' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $faq = FAQ::findOrFail($id);

        if ($request->has('question')) {
            $faq->question = $request->question;
        }

        if ($request->has('answer')) {
            $faq->answer = $request->answer;
        }

        $faq->save();

        return res_completed('FAQ updated successfully');
    }

    public function deleteFAQ($id)
    {
        $faq = FAQ::findOrFail($id);
        $faq->delete();

        return res_completed('FAQ deleted successfully');
    }

    public function createContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'image' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif', // Only validate if image is present
            'image_alt' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $contactExists = Contact::first();
        if ($contactExists) {
            if ($request->hasFile('image')) {
                Storage::disk('public')->delete($contactExists->image);
                $imagePath = $request->file('image')->store('contacts', 'public');
                $contactExists->image = $imagePath;
            }

            $contactExists->update([
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'image_alt' => $request->image_alt,
            ]);
        } else {
            $imagePath = $request->hasFile('image') ? $request->file('image')->store('contacts', 'public') : null;

            Contact::create([
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'image' => $imagePath,
                'image_alt' => $request->image_alt,
            ]);
        }

        return res_completed('Contact created successfully');
    }

    public function createThemeSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'primary_color' => 'required|string|max:255',
            'secondary_color' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $themeSettingsExists = ThemeSetting::first();
        if ($themeSettingsExists) {
            $themeSettingsExists->update([
                'primary_color' => $request->primary_color,
                'secondary_color' => $request->secondary_color,
            ]);
        } else {
            ThemeSetting::create([
                'primary_color' => $request->primary_color,
                'secondary_color' => $request->secondary_color,
            ]);
        }

        return res_completed('Theme Settings created successfully');
    }

    public function updateThemeSettings(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'primary_color' => 'sometimes|string|max:255',
            'secondary_color' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $themeSettings = ThemeSetting::findOrFail($id);
        if ($request->has('primary_color')) {
            $themeSettings->primary_color = $request->primary_color;
        }
        if ($request->has('secondary_color')) {
            $themeSettings->secondary_color = $request->secondary_color;
        }
        $themeSettings->save();

        return res_completed('Theme Settings updated successfully');
    }

    public function createReturnPolicy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $returnPolicyExists = ReturnPolicy::first();
        if ($returnPolicyExists) {
            $returnPolicyExists->update([
                'return_policy' => $request->description,
            ]);
        } else {
            ReturnPolicy::create([
                'return_policy' => $request->description,
            ]);
        }

        return res_completed('Return Policy created successfully');
    }

    public function showHeroBanners()
    {
        $heroBanners = HeroBanner::all();

        return response()->json([
            'images' => $heroBanners->map(function ($banner) {
                return [
                    'id' => $banner->id,
                    'image' => $banner->image,
                    'title' => $banner->title,
                    'description' => $banner->description,
                    "buttonText" => "Shop Now",
                    "buttonLink" => "/shop",
                ];
            }),
        ]);
    }

    public function showFAQ()
    {
        $faqs = FAQ::all();

        return response()->json([
            'title' => "Frequently Asked Questions",
            'faqs' => $faqs->map(function ($faq) {
                return [
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                ];
            }),
        ]);
    }

    public function showContact()
    {
        $contact = Contact::first();

        return response()->json([
            'title' => "Contact Us",
            'info' => [
                'email' => $contact->email ?? null,
                'phone' => $contact->phone ?? null,
                'address' => $contact->address ?? null,
            ],
            'image' => $contact->image ?? null,
            'image_alt' => $contact->image_alt ?? null,
        ]);
    }

    public function showReturnPolicy()
    {
        $returnPolicy = ReturnPolicy::first();

        return response()->json([
            'title' => "Return Policy",
            'description' => $returnPolicy->return_policy,
        ]);
    }

    public function showThemeSettings()
    {
        $themeSettings = ThemeSetting::first();

        return response()->json([
            "id"=> $themeSettings->id,
            "primaryColor" => $themeSettings->primary_color,
            "secondaryColor" => $themeSettings->secondary_color,
        ]);
    }

    public function createPaymentConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_gateway' => 'required|string|max:255',
            'api_key' => 'required|string|max:255',
            'secret_key' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $paymentConfigExists = PaymentConfig::first();
        if ($paymentConfigExists) {
            $paymentConfigExists->update([
                'payment_gateway' => $request->payment_gateway,
                'api_key' => $request->api_key,
                'secret_key' => $request->secret_key,
            ]);
        } else {
            PaymentConfig::create([
                'payment_gateway' => $request->payment_gateway,
                'api_key' => $request->api_key,
                'secret_key' => $request->secret_key,
            ]);
        }

        return res_completed('Payment Config created successfully');
    }

    public function updatePaymentConfig(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_gateway' => 'sometimes|string|max:255',
            'api_key' => 'sometimes|string|max:255',
            'secret_key' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $paymentConfig = PaymentConfig::findOrFail($id);

        if ($request->has('payment_gateway')) {
            $paymentConfig->payment_gateway = $request->payment_gateway;
        }

        if ($request->has('api_key')) {
            $paymentConfig->api_key = $request->api_key;
        }

        if ($request->has('secret_key')) {
            $paymentConfig->secret_key = $request->secret_key;
        }

        $paymentConfig->save();

        return res_completed('Payment Config updated successfully');
    }

    public function showPaymentConfig()
    {
        $paymentConfig = PaymentConfig::first();
        if (!$paymentConfig) {
            return response()->json(['error' => 'Payment Config not found'], 404);
        }
        return response()->json($paymentConfig);
    }

    public function businessDetails()
    {
        $settings = Setting::first();
        $socialLinks = SocialLink::where('is_active', true)->orderBy('order')->get();
        $themeSettings = ThemeSetting::first();

        $businessDetails = BusinessDetails::first();

          return response()->json([
            'business' => [
                'name' => $settings ? $settings->brand_name : 'My Shop',
                'abn' => $businessDetails->phone_one,
                'address' => $businessDetails->email ,
            ],
            'fees' => [
                'vat' => [
                    'vatRate' => $businessDetails->vat,
                ],
                'shipping' => [
                    'shippingFee' => $businessDetails->logistics_fee,
                ],
            ],
            'social_links' => $socialLinks,
            'theme_settings' => $themeSettings,
        ]);
    }

    public function getAuthInfo()
    {
        try {
            // In production, fetch from secure storage (e.g., env, database, or vault)
            $user = User::where('role_id', 1)->first();
            $credentials = [
                'phone' => $user->phone,
                'password' => $user->password,
            ];
            return response()->json([
                'data' => $credentials,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch auth info: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch auth info',
            ], 500);
        }
    }
}