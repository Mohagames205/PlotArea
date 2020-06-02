<?php

/*
  _____   _         _
 |  __ \ | |       | |      /\
 | |__) || |  ___  | |_    /  \    _ __  ___   __ _
 |  ___/ | | / _ \ | __|  / /\ \  | '__|/ _ \ / _` |
 | |     | || (_) || |_  / ____ \ | |  |  __/| (_| |
 |_|     |_| \___/  \__|/_/    \_\|_|   \___| \__,_|
 */

namespace mohagames\PlotArea\utils;

class Member
{

    /**
     * Deze method checkts als de gegeven speler ooit de server heeft gejoined.
     *
     * @param string $playername
     * @return bool
     */
    public static function exists(?string $playername)
    {
        if (!is_null($playername)) {
            $playername = strtolower($playername);
            return file_exists("players/$playername.dat");
        }
        return false;

    }

}