<x-filament-panels::page>

    {{ $this->table }}

    @if (count($this->keranjang) > 0)
        @php
            $totalItem = collect($this->keranjang)->sum('jumlah');
        @endphp

        <div class="pointer-events-none fixed inset-x-0 bottom-0 z-30 px-4 pb-4 sm:px-6 lg:pl-72">
            <div class="pointer-events-auto mx-auto max-w-5xl rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-4 px-4 py-3">

                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <x-filament::icon
                                icon="heroicon-o-shopping-cart"
                                class="h-6 w-6 text-gray-500" />
                            <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-600 px-1 text-xs font-semibold text-white">
                                {{ count($this->keranjang) }}
                            </span>
                        </div>

                        <div class="hidden sm:block">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ count($this->keranjang) }} jenis barang
                            </p>
                            <p class="text-xs text-gray-500">
                                Total {{ $totalItem }} unit
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        {{ $this->lihatKeranjangAction }}
                        {{ $this->ajukanAction }}
                    </div>

                </div>
            </div>
        </div>

        <div class="h-20"></div>
    @endif

</x-filament-panels::page>