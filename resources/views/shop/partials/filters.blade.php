<aside class="lg:w-64 shrink-0" x-data="{ show: false }">
    <button class="mb-4 w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium lg:hidden" @click="show = !show">
        Filters
    </button>

    <form method="GET" class="space-y-6" :class="{ 'hidden lg:block': !show }" x-bind:class="show ? '' : 'hidden lg:block'">
        @if(request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

        <div>
            <p class="text-sm font-semibold">Price</p>
            <div class="mt-2 flex items-center gap-2">
                <input type="number" name="min_price" value="{{ request('min_price') }}" min="0" placeholder="Min" class="w-full rounded-md border-gray-300 text-sm" aria-label="Minimum price">
                <span class="text-gray-400">–</span>
                <input type="number" name="max_price" value="{{ request('max_price') }}" min="0" placeholder="Max" class="w-full rounded-md border-gray-300 text-sm" aria-label="Maximum price">
            </div>
        </div>

        @if($filterData['brands']->isNotEmpty())
            <div>
                <p class="text-sm font-semibold">Brand</p>
                <div class="mt-2 space-y-1">
                    @foreach($filterData['brands'] as $brand)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="brand[]" value="{{ $brand->slug }}" @checked(in_array($brand->slug, (array) request('brand', []))) class="rounded border-gray-300">
                            {{ $brand->name }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        @foreach($filterData['attributes'] as $attribute)
            @if($attribute->values->isNotEmpty())
                <div>
                    <p class="text-sm font-semibold">{{ $attribute->name }}</p>
                    @php $selected = array_filter(explode(',', (string) request("attr.{$attribute->slug}", ''))); @endphp
                    <div class="mt-2 flex flex-wrap gap-1.5" x-data>
                        @foreach($attribute->values as $value)
                            <label class="cursor-pointer rounded-full border px-3 py-1 text-xs {{ in_array($value->slug, $selected) ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-300' }}">
                                <input type="checkbox" class="sr-only" name="attr_raw[{{ $attribute->slug }}][]" value="{{ $value->slug }}" @checked(in_array($value->slug, $selected)) onchange="this.form.requestSubmit()">
                                {{ $value->value }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <div class="space-y-1 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="in_stock" value="1" @checked(request('in_stock')) class="rounded border-gray-300"> In stock only</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="on_sale" value="1" @checked(request('on_sale')) class="rounded border-gray-300"> On sale</label>
        </div>

        <div>
            <p class="text-sm font-semibold">Minimum rating</p>
            <select name="rating" class="mt-2 w-full rounded-md border-gray-300 text-sm">
                <option value="">Any</option>
                @foreach([4, 3, 2] as $stars)
                    <option value="{{ $stars }}" @selected(request('rating') == $stars)>{{ $stars }}★ &amp; up</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button class="flex-1 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Apply</button>
            <a href="{{ url()->current() }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm">Reset</a>
        </div>
    </form>

    <script>
        // Convert attr_raw[slug][] checkboxes to attr[slug]=a,b before submit.
        document.currentScript.previousElementSibling.addEventListener('formdata', (e) => {
            const grouped = {};
            for (const [key, value] of [...e.formData.entries()]) {
                const match = key.match(/^attr_raw\[(.+)\]\[\]$/);
                if (match) {
                    (grouped[match[1]] ??= []).push(value);
                    e.formData.delete(key);
                }
            }
            for (const [slug, values] of Object.entries(grouped)) {
                e.formData.set(`attr[${slug}]`, values.join(','));
            }
        });
    </script>
</aside>
