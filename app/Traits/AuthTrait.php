<?php
namespace App\Traits;

trait AuthTrait{

    public function generateCode(){

    }


    public function hashString($string) {
        $h = 7;
        $string = str_split($string);
        $letters = ['a', 'c', 'd', 'e', 'g', 'i', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'w'];
        for ($index = 0; $index < count($string); $index++) {
            $h = ($h * 37 + array_search($string[$index], $letters));
        }
        return $h;
    }

    public static function unHash($number) {
        /*
            steps:
            create an array to hold modulo results
            initialize num = number
            perform a loop to get the modulo of the new result from number/37
            get modulo of result and push to array(text)
        */
        $text = [];
        $num = $number;
        $mod = $num % 37;
        array_push($text, intval($mod)); // 2)
        for ($index = 0; $index < 9 - 1; $index++) {
            $num = $num / 37;
            $mod = $num % 37;
            array_push($text, intval($mod)); // 2)
        }
        $encryption = new Encryption;
        $res = $encryption->convert_array_of_numbers_to_string($text);
        return $res;
    }

}

class Encryption{
    public function convert_array_of_numbers_to_string($num_array) {
        $string = [];
        $letters = ['a', 'c', 'd', 'e', 'g', 'i', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'w'];
        for ($index = 0; $index < count($num_array); $index++) {
            array_push($string, $letters[$num_array[$index]]);
        }
        $string = array_reverse($string);
        $string = implode($string);
        return $string;
    }
}
