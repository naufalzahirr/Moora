<?php

return [
    // DomPDF is the reliable default for local and shared-hosting deployments.
    // Chrome rendering can be opted into when a stable browser binary is available.
    'engine' => env('PDF_ENGINE', 'dompdf'),
    'chrome_binary' => env('CHROME_BINARY'),
    'render_timeout' => (int) env('PDF_RENDER_TIMEOUT', 45),
];
