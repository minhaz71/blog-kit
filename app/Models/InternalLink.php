<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalLink extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function source()
    {
        return $this->morphTo();
    }

    public function target()
    {
        return $this->morphTo();
    }
}
