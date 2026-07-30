<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkTarget extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function target()
    {
        return $this->morphTo();
    }
}
