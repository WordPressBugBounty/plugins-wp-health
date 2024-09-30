<?php

if (!class_exists('UmbrellaTableType', false)):
    class UmbrellaTableType
    {
        const REGULAR = 0;
        const VIEW = 1;
        const PROCEDURE = 2;
        const FUNC = 3;
    }
endif;
