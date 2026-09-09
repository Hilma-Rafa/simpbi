<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/**
 * Kegagalan pengiriman pesan WhatsApp.
 *
 * Dibuat tersendiri agar job pengiriman dapat membedakan penolakan dari
 * gerbang WhatsApp — yang wajar terjadi dan cukup dicatat pada kolom
 * `error_message` — dengan galat pemrograman yang memang harus dimunculkan.
 */
class PengirimanGagal extends RuntimeException
{
}
