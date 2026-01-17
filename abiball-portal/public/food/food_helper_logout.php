<?php
declare(strict_types=1);

// public/food/food_helper_logout.php

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Auth/FoodHelperContext.php';

Bootstrap::init();
FoodHelperContext::logout('/food/food_helper_login.php');
