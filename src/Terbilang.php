<?php namespace Riskihajar\Terbilang;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Config;

class Terbilang{

    protected $hyphen;
    protected $conjunction;
    protected $separator;
    protected $negative;
    protected $decimal;
    protected $dictionary;
    protected $prefix;
    protected $suffix;
    protected $prenum;

    protected $lang;

    protected $shortDividers = [
        'kilo' => 1000,
        'million' => 1000000,
        'billion' => 1000000000,
        'trillion' => 1000000000000
    ];

    protected $shortDividerAliases = [
        'k' => 'kilo',
        'm' => 'million',
        'b' => 'billion',
        't' => 'trillion',
    ];

    public function __construct()
    {
        $this->hyphen      = Lang::get('terbilang::terbilang.hyphen');
        $this->conjunction = Lang::get('terbilang::terbilang.conjunction');
        $this->separator   = Lang::get('terbilang::terbilang.separator');
        $this->negative    = Lang::get('terbilang::terbilang.negative');
        $this->decimal     = Lang::get('terbilang::terbilang.decimal');
        $this->dictionary  = Lang::get('terbilang::terbilang.dictionary');
        $this->prefix      = Lang::get('terbilang::terbilang.prefix');
        $this->suffix      = Lang::get('terbilang::terbilang.suffix');
        $this->prenum      = Lang::get('terbilang::terbilang.prenum');
        $this->short       = Lang::get('terbilang::terbilang.short');

        $this->lang = Config::get('app.locale');
    }

    public function make($number, $suffix=false, $prefix=false)
    {
        $prefix = $prefix ? $prefix : $this->prefix;
        $suffix = $suffix ? $suffix : $this->suffix;

        if (!is_numeric($number)) {
            return false;
        }

        // parse quoted value
        $number = doubleval($number);

        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
            // overflow
            trigger_error(
                'NumToWords only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
                E_USER_WARNING
            );
            return false;
        }

        if ($number < 0) {
            return $this->negative . $this->make(abs($number));
        }

        $string = $fraction = null;

        if (strpos($number, '.') !== false) {
            list($number, $fraction) = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $this->dictionary[$number];
                break;
            case $number < 100:
                $tens   = ((int) ($number / 10)) * 10;
                $units  = $number % 10;
                $string = $this->dictionary[$tens];
                if ($units) {
                    $string .= $this->hyphen . $this->dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds  = $number / 100;
                $remainder = $number % 100;
                if($this->prenum){
                    $lead = (int) substr($number, 0, 1);
                    $string = ($lead === 1 ? $this->prenum : $this->dictionary[$hundreds] . ' ') . $this->dictionary[100];
                }else{
                    $string = $this->dictionary[$hundreds] . ' ' . $this->dictionary[100];
                }
                if ($remainder) {
                    $string .= $this->conjunction . $this->make($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int) ($number / $baseUnit);
                $remainder = $number % $baseUnit;
                if($this->prenum) {
                    $string = ($numBaseUnits === 1 && $baseUnit < 1000000 ? $this->prenum : $this->make($numBaseUnits) . ' ') . $this->dictionary[$baseUnit];
                }else{
                    $string = $this->make($numBaseUnits) . ' ' . $this->dictionary[$baseUnit];
                }

                if ($remainder) {
                    $string .= $remainder < 100 ? $this->conjunction : $this->separator;
                    $string .= $this->make($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $this->decimal;
            $words = array();
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $this->dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $prefix . $string . $suffix;
    }

    public function short($number, $format=null)
    {
        if(is_null($format)){
            $format = config('terbilang.short', 'million', 'm');
        }

        /* If Divider Using Alias or short hand */
        if( strlen($format) === 1 ){
            $format = array_get($this->shortDividerAliases, $format, 'million');
        }

        /* Get Divider */
        $divider = array_get($this->shortDividers, $format, 1);
        /* Suffix */
        $suffix = array_get($this->short, $format);

        /* Process result */
        $result = round(doubleval($number) / $divider, 2);

        return sprintf('%g%s', $result, $suffix);
    }

    public function date($date, $format='Y-m-d')
    {
        if( ! self::is_carbon($date) ) $date = date_create_from_format($format, $date);
        if ( ! is_object($date) ){
            return 'Invalid Date or Format';
        }

        $j = $date->format('j');
        $n = $date->format('n');
        $Y = $date->format('Y');

        $day = $this->make($j);
        $month = strtolower(Lang::get('terbilang::date.month.'.$n));
        $year = $this->make($Y);

        $template = config('terbilang.output.date', '{DAY} {MONTH} {YEAR}');

        return str_replace(['{DAY}', '{MONTH}', '{YEAR}'], [$day, $month, $year], $template);
    }

    public function time($time, $format='h:i:s')
    {
        if( ! self::is_carbon($time) ) $time = date_create_from_format($format, $time);

        if ( ! is_object($time) ){
            return 'Invalid Date or Format';
        }

        $G = $time->format('G');
        $i = $time->format('i');
        $s = (int) $time->format('s') ?: null;

        $separator  = Lang::get('terbilang::date.time.minute-separator');
        $minute_str = Lang::get('terbilang::date.time.minute');
        $second_str = $s ? Lang::get('terbilang::date.time.second') : null;

        $hour = $this->make($G);
        $minute = $this->make($i);
        $second = $this->make($s);

        $template = config('terbilang.output.time', '{HOUR} {SEPARATOR} {MINUTE} {MINUTE_LABEL} {SECOND} {SECOND_LABEL}');

        return str_replace([
            '{HOUR}','{SEPARATOR}','{MINUTE}','{MINUTE_LABEL}','{SECOND}','{SECOND_LABEL}'
        ],
        [
            $hour, $separator, $minute, $minute_str, $second, $second_str
        ], $template);
    }

    protected static function is_carbon($object)
    {
        if( is_object($object) ) {
            if (get_class($object) === 'Carbon\\Carbon') {
                return true;
            }
        }

        return false;
    }

    public function datetime($datetime, $format='Y-m-d h:i:s')
    {
        if( ! self::is_carbon($datetime) ) $datetime = date_create_from_format($format, $datetime);

        if ( ! is_object($datetime) ){
            return 'Invalid Date or Format';
        }

        $date = $datetime->format('Y-m-d');
        $time = $datetime->format('h:i:s');
        $separator = Lang::get('terbilang::date.time.dt-separator');

        return sprintf('%s %s %s', $this->date($date), $separator, $this->time($time));
    }

    public function roman($number, $lowercase=false)
    {
        $n = (int) $number;
        $string = '';

        $romanList = array(
            'M'  => 1000,
            'CM' => 900,
            'D'  => 500,
            'CD' => 400,
            'C'  => 100,
            'XC' => 90,
            'L'  => 50,
            'XL' => 40,
            'X'  => 10,
            'IX' => 9,
            'V'  => 5,
            'IV' => 4,
            'I'  => 1
        );

        foreach($romanList as $roman => $number)
        {
            $matches = intval($n / $number);
            $string .= str_repeat($roman, $matches);
            $n = $n % $number;
        }

        if($lowercase) $string = strtolower($string);

        return $string;
    }
}
