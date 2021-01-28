<?php
use Carbon\CarbonInterval;

function timeStrampDiffFormatted($t1, $t2)
{
    $days = $t1->diffInDays($t2);
    $hours = $t1->diffInHours($t2->subDays($days));
    $minutes = $t1->diffInMinutes($t2->subHours($hours));
    $seconds = $t1->diffInSeconds($t2->subMinutes($minutes));
    return CarbonInterval::days($days)->hours($hours)->minutes($minutes)->seconds($seconds)->forHumans();
};

function diffInMins($t1, $t2)
{
    $minutes = $t1->diffInMinutes($t2);
    return $minutes;
}

function returnAcronym($string)
{
    $words = explode(' ', "$string");
    $acronym = '';
    foreach ($words as $w) {
        $acronym .= $w[0];
    }
    $firstTwoChars = strtoupper(mb_substr($acronym, 0, 2, 'UTF-8'));
    return $firstTwoChars;
}
