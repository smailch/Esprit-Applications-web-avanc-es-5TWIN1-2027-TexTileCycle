<?php

namespace App\Modules\Ateliers\Http\Requests;

use App\Modules\Ateliers\Http\Requests\Concerns\ValidatesServiceFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    use ValidatesServiceFields;
}
