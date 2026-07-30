<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'states' => 'array',
            'cities' => 'array',
            'postcodes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function methods()
    {
        return $this->hasMany(ShippingMethod::class)->orderBy('sort_order');
    }

    public function activeMethods()
    {
        return $this->methods()->where('is_active', true);
    }

    /**
     * Does this zone cover the given destination? Empty criteria match everything,
     * so a zone with null countries acts as "rest of the world".
     */
    public function matches(?string $country, ?string $state = null, ?string $city = null, ?string $postcode = null): bool
    {
        $in = function (?array $list, ?string $value): bool {
            if (empty($list)) {
                return true;
            }

            return $value !== null && in_array(strtolower($value), array_map('strtolower', $list));
        };

        return $in($this->countries, $country)
            && $in($this->states, $state)
            && $in($this->cities, $city)
            && $in($this->postcodes, $postcode);
    }

    /** Specificity used to prefer the most precise matching zone. */
    public function specificity(): int
    {
        return (empty($this->postcodes) ? 0 : 8)
            + (empty($this->cities) ? 0 : 4)
            + (empty($this->states) ? 0 : 2)
            + (empty($this->countries) ? 0 : 1);
    }
}
