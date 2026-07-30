<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return view('account.addresses', ['addresses' => $request->user()->addresses]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $address = $request->user()->addresses()->create($data);
        $this->syncDefault($address);

        return back()->with('success', 'Address saved.');
    }

    public function update(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $address->update($this->validated($request));
        $this->syncDefault($address);

        return back()->with('success', 'Address updated.');
    }

    public function destroy(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $address->delete();

        return back()->with('success', 'Address removed.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:billing,shipping'],
            'label' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address_line_1' => ['required', 'string', 'max:200'],
            'address_line_2' => ['nullable', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2', \Illuminate\Validation\Rule::in(array_keys(store_countries()))],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    protected function syncDefault(Address $address): void
    {
        if ($address->is_default) {
            $address->user->addresses()
                ->where('type', $address->type)
                ->whereKeyNot($address->id)
                ->update(['is_default' => false]);
        }
    }
}
