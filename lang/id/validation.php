<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Baris Bahasa Validasi
    |--------------------------------------------------------------------------
    |
    | Terjemahan pesan bawaan Laravel (Illuminate\Translation\lang\en\validation)
    | ke bahasa Indonesia. Kuncinya sama persis dengan berkas bawaan, begitu pula
    | penanda tempat (:attribute, :other, :value, dan sejenisnya). Pesan galat
    | yang ditulis manual oleh aplikasi tidak melewati berkas ini.
    |
    */

    'accepted' => 'Kolom :attribute harus diterima.',
    'accepted_if' => 'Kolom :attribute harus diterima jika :other adalah :value.',
    'active_url' => 'Kolom :attribute harus berupa URL yang valid.',
    'after' => 'Kolom :attribute harus berupa tanggal setelah :date.',
    'after_or_equal' => 'Kolom :attribute harus berupa tanggal setelah atau sama dengan :date.',
    'alpha' => 'Kolom :attribute hanya boleh berisi huruf.',
    'alpha_dash' => 'Kolom :attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => 'Kolom :attribute hanya boleh berisi huruf dan angka.',
    'any_of' => 'Kolom :attribute tidak valid.',
    'array' => 'Kolom :attribute harus berupa larik.',
    'ascii' => 'Kolom :attribute hanya boleh berisi karakter alfanumerik dan simbol bita tunggal.',
    'before' => 'Kolom :attribute harus berupa tanggal sebelum :date.',
    'before_or_equal' => 'Kolom :attribute harus berupa tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => 'Kolom :attribute harus memiliki antara :min dan :max item.',
        'file' => 'Kolom :attribute harus berukuran antara :min dan :max kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai antara :min dan :max.',
        'string' => 'Kolom :attribute harus terdiri atas :min sampai :max karakter.',
    ],
    'boolean' => 'Kolom :attribute harus bernilai benar atau salah.',
    'can' => 'Kolom :attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi kolom :attribute tidak cocok.',
    'contains' => 'Kolom :attribute tidak memuat nilai yang diwajibkan.',
    'current_password' => 'Kata sandi salah.',
    'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
    'date_equals' => 'Kolom :attribute harus berupa tanggal yang sama dengan :date.',
    'date_format' => 'Kolom :attribute harus sesuai dengan format :format.',
    'decimal' => 'Kolom :attribute harus memiliki :decimal angka desimal.',
    'declined' => 'Kolom :attribute harus ditolak.',
    'declined_if' => 'Kolom :attribute harus ditolak jika :other adalah :value.',
    'different' => 'Kolom :attribute dan :other harus berbeda.',
    'digits' => 'Kolom :attribute harus terdiri atas :digits digit.',
    'digits_between' => 'Kolom :attribute harus terdiri atas :min sampai :max digit.',
    'dimensions' => 'Kolom :attribute memiliki dimensi gambar yang tidak valid.',
    'distinct' => 'Kolom :attribute memiliki nilai yang duplikat.',
    'doesnt_contain' => 'Kolom :attribute tidak boleh memuat salah satu dari berikut: :values.',
    'doesnt_end_with' => 'Kolom :attribute tidak boleh diakhiri dengan salah satu dari berikut: :values.',
    'doesnt_start_with' => 'Kolom :attribute tidak boleh diawali dengan salah satu dari berikut: :values.',
    'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
    'encoding' => 'Kolom :attribute harus dikodekan dalam :encoding.',
    'ends_with' => 'Kolom :attribute harus diakhiri dengan salah satu dari berikut: :values.',
    'enum' => ':attribute yang dipilih tidak valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'extensions' => 'Kolom :attribute harus memiliki salah satu ekstensi berikut: :values.',
    'file' => 'Kolom :attribute harus berupa berkas.',
    'filled' => 'Kolom :attribute harus memiliki nilai.',
    'gt' => [
        'array' => 'Kolom :attribute harus memiliki lebih dari :value item.',
        'file' => 'Kolom :attribute harus berukuran lebih dari :value kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai lebih dari :value.',
        'string' => 'Kolom :attribute harus terdiri atas lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => 'Kolom :attribute harus memiliki :value item atau lebih.',
        'file' => 'Kolom :attribute harus berukuran sekurang-kurangnya :value kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai sekurang-kurangnya :value.',
        'string' => 'Kolom :attribute harus terdiri atas sekurang-kurangnya :value karakter.',
    ],
    'hex_color' => 'Kolom :attribute harus berupa warna heksadesimal yang valid.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'in_array' => 'Kolom :attribute harus ada di dalam :other.',
    'in_array_keys' => 'Kolom :attribute harus memuat sekurang-kurangnya salah satu kunci berikut: :values.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'ip' => 'Kolom :attribute harus berupa alamat IP yang valid.',
    'ipv4' => 'Kolom :attribute harus berupa alamat IPv4 yang valid.',
    'ipv6' => 'Kolom :attribute harus berupa alamat IPv6 yang valid.',
    'json' => 'Kolom :attribute harus berupa string JSON yang valid.',
    'list' => 'Kolom :attribute harus berupa daftar.',
    'lowercase' => 'Kolom :attribute harus berupa huruf kecil.',
    'lt' => [
        'array' => 'Kolom :attribute harus memiliki kurang dari :value item.',
        'file' => 'Kolom :attribute harus berukuran kurang dari :value kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai kurang dari :value.',
        'string' => 'Kolom :attribute harus terdiri atas kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => 'Kolom :attribute tidak boleh memiliki lebih dari :value item.',
        'file' => 'Kolom :attribute harus berukuran paling besar :value kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai paling besar :value.',
        'string' => 'Kolom :attribute harus terdiri atas paling banyak :value karakter.',
    ],
    'mac_address' => 'Kolom :attribute harus berupa alamat MAC yang valid.',
    'max' => [
        'array' => 'Kolom :attribute tidak boleh memiliki lebih dari :max item.',
        'file' => 'Kolom :attribute tidak boleh berukuran lebih dari :max kilobita.',
        'numeric' => 'Kolom :attribute tidak boleh bernilai lebih dari :max.',
        'string' => 'Kolom :attribute tidak boleh terdiri atas lebih dari :max karakter.',
    ],
    'max_digits' => 'Kolom :attribute tidak boleh terdiri atas lebih dari :max digit.',
    'mimes' => 'Kolom :attribute harus berupa berkas dengan tipe: :values.',
    'mimetypes' => 'Kolom :attribute harus berupa berkas dengan tipe: :values.',
    'min' => [
        'array' => 'Kolom :attribute harus memiliki sekurang-kurangnya :min item.',
        'file' => 'Kolom :attribute harus berukuran sekurang-kurangnya :min kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai sekurang-kurangnya :min.',
        'string' => 'Kolom :attribute harus terdiri atas sekurang-kurangnya :min karakter.',
    ],
    'min_digits' => 'Kolom :attribute harus terdiri atas sekurang-kurangnya :min digit.',
    'missing' => 'Kolom :attribute harus tidak ada.',
    'missing_if' => 'Kolom :attribute harus tidak ada jika :other adalah :value.',
    'missing_unless' => 'Kolom :attribute harus tidak ada kecuali :other adalah :value.',
    'missing_with' => 'Kolom :attribute harus tidak ada jika :values ada.',
    'missing_with_all' => 'Kolom :attribute harus tidak ada jika :values semuanya ada.',
    'multiple_of' => 'Kolom :attribute harus merupakan kelipatan dari :value.',
    'not_in' => ':attribute yang dipilih tidak valid.',
    'not_regex' => 'Format kolom :attribute tidak valid.',
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'password' => [
        'letters' => 'Kolom :attribute harus memuat sekurang-kurangnya satu huruf.',
        'mixed' => 'Kolom :attribute harus memuat sekurang-kurangnya satu huruf besar dan satu huruf kecil.',
        'numbers' => 'Kolom :attribute harus memuat sekurang-kurangnya satu angka.',
        'symbols' => 'Kolom :attribute harus memuat sekurang-kurangnya satu simbol.',
        'uncompromised' => ':attribute yang diberikan pernah muncul dalam kebocoran data. Pilihlah :attribute yang berbeda.',
    ],
    'present' => 'Kolom :attribute harus ada.',
    'present_if' => 'Kolom :attribute harus ada jika :other adalah :value.',
    'present_unless' => 'Kolom :attribute harus ada kecuali :other adalah :value.',
    'present_with' => 'Kolom :attribute harus ada jika :values ada.',
    'present_with_all' => 'Kolom :attribute harus ada jika :values semuanya ada.',
    'prohibited' => 'Kolom :attribute tidak diperbolehkan.',
    'prohibited_if' => 'Kolom :attribute tidak diperbolehkan jika :other adalah :value.',
    'prohibited_if_accepted' => 'Kolom :attribute tidak diperbolehkan jika :other diterima.',
    'prohibited_if_declined' => 'Kolom :attribute tidak diperbolehkan jika :other ditolak.',
    'prohibited_unless' => 'Kolom :attribute tidak diperbolehkan kecuali :other termasuk dalam :values.',
    'prohibits' => 'Kolom :attribute melarang :other untuk ada.',
    'regex' => 'Format kolom :attribute tidak valid.',
    'required' => 'Kolom :attribute wajib diisi.',
    'required_array_keys' => 'Kolom :attribute harus memuat entri untuk: :values.',
    'required_if' => 'Kolom :attribute wajib diisi jika :other adalah :value.',
    'required_if_accepted' => 'Kolom :attribute wajib diisi jika :other diterima.',
    'required_if_declined' => 'Kolom :attribute wajib diisi jika :other ditolak.',
    'required_unless' => 'Kolom :attribute wajib diisi kecuali :other termasuk dalam :values.',
    'required_with' => 'Kolom :attribute wajib diisi jika :values ada.',
    'required_with_all' => 'Kolom :attribute wajib diisi jika :values semuanya ada.',
    'required_without' => 'Kolom :attribute wajib diisi jika :values tidak ada.',
    'required_without_all' => 'Kolom :attribute wajib diisi jika tidak ada satu pun dari :values.',
    'same' => 'Kolom :attribute harus sama dengan :other.',
    'size' => [
        'array' => 'Kolom :attribute harus memuat :size item.',
        'file' => 'Kolom :attribute harus berukuran :size kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai :size.',
        'string' => 'Kolom :attribute harus terdiri atas :size karakter.',
    ],
    'starts_with' => 'Kolom :attribute harus diawali dengan salah satu dari berikut: :values.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'timezone' => 'Kolom :attribute harus berupa zona waktu yang valid.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => ':attribute gagal diunggah.',
    'uppercase' => 'Kolom :attribute harus berupa huruf besar.',
    'url' => 'Kolom :attribute harus berupa URL yang valid.',
    'ulid' => 'Kolom :attribute harus berupa ULID yang valid.',
    'uuid' => 'Kolom :attribute harus berupa UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Baris Bahasa Validasi Khusus
    |--------------------------------------------------------------------------
    |
    | Pesan khusus untuk atribut tertentu dengan konvensi "atribut.aturan".
    | Belum ada yang dipakai; strukturnya dipertahankan seperti berkas bawaan.
    |
    */

    'custom' => [
        'nama-atribut' => [
            'nama-aturan' => 'pesan-khusus',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Atribut Validasi Khusus
    |--------------------------------------------------------------------------
    |
    | Nama atribut yang lebih mudah dibaca menggantikan penanda tempat
    | :attribute. Form Filament sudah membawa labelnya sendiri; bagian ini
    | hanya berlaku bagi validasi yang tidak membawa label, dengan nama yang
    | sama seperti label di form.
    |
    */

    'attributes' => [
        'name' => 'nama',
        'username' => 'username',
        'email' => 'email',
        'password' => 'kata sandi',
        'password_confirmation' => 'konfirmasi kata sandi',
        'current_password' => 'kata sandi saat ini',
        'nip' => 'NIP',
        'no_hp' => 'nomor WhatsApp',
        'role' => 'peran',
        'tim_id' => 'tim kerja',
        'status_aktif' => 'status aktif',
    ],

];
