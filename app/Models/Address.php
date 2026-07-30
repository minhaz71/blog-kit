<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function toOrderArray(): array
    {
        return $this->only([
            'first_name', 'last_name', 'company', 'phone', 'address_line_1',
            'address_line_2', 'city', 'state', 'postal_code', 'country',
        ]);
    }
}
