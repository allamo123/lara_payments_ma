<?php

namespace Ma\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Ma\Payments\Traits\SetVariables;
use Ma\Payments\Traits\SetRequiredFields;

class BaseController 
{
	use SetVariables,SetRequiredFields;
}