<?php
declare(strict_types=1);

// Blockiert die Seite für Nutzer, aber erhält den Code für spätere Aktivierung
echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Essensbestellung</title><link rel="stylesheet" href="/assets/css/main.css"><style>body{background:#fff;font-family:sans-serif;margin:0;padding:0;} .center-box{max-width:500px;margin:80px auto;padding:32px 24px;background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,0.07);} .center-box h1{font-size:1.5rem;margin-bottom:1rem;} .center-box p{font-size:1.1rem;} .center-box .alert{background:#fff3cd;border:1px solid #ffeeba;padding:12px 16px;border-radius:8px;color:#856404;margin-bottom:1rem;}</style></head><body><div class="center-box"><div class="alert"><strong>Essensbestellungen sind aktuell nicht möglich.</strong></div><p>Wir müssen die Details noch mit dem Caterer abklären. Sobald Bestellungen möglich sind, informieren wir euch hier.</p></div></body></html>';
// Ursprünglicher Code bleibt erhalten:
// require_once __DIR__ . '/../../src/Controller/FoodOrderController.php';
// FoodOrderController::show();

