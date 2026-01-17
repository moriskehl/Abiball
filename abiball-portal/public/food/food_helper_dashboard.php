<?php
declare(strict_types=1);

// public/food/food_helper_dashboard.php

require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Auth/FoodHelperContext.php';
require_once __DIR__ . '/../../src/Http/Response.php';
require_once __DIR__ . '/../../src/View/Layout.php';
require_once __DIR__ . '/../../src/View/Helpers.php';

Bootstrap::init();
FoodHelperContext::requireFoodHelper('/food/food_helper_login.php');

$helperName = FoodHelperContext::helperName();

Layout::header('Essensausgabe – Scanner');
?>
<script src="https://cdn.jsdelivr.net/npm/@zxing/library@0.21.0/umd/index.min.js"></script>
<style>
    #food-scanner-container {
        padding: 16px;
    }

    #food-scanner-container .container {
        max-width: 600px;
        margin: 0 auto;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .header h1 {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 700;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--muted);
        font-size: 0.92rem;
    }

    .logout-btn {
        padding: 6px 12px;
        background: rgba(255,77,90,.1);
        border: 1px solid rgba(255,77,90,.35);
        border-radius: 8px;
        color: var(--text);
        text-decoration: none;
        font-size: 0.86rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }

    .logout-btn:hover {
        background: rgba(255,77,90,.15);
    }

    .card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
        margin-bottom: 20px;
    }

    .card-head {
        padding: 18px;
        border-bottom: 1px solid var(--border);
        background: linear-gradient(180deg, var(--surface2), transparent);
    }

    .card-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
    }

    .card-body {
        padding: 20px;
    }

    .scanner-container {
        position: relative;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
        width: 100%;
        max-width: 600px;
        margin: 0 auto 16px auto;
    }

    #scanner {
        width: 100%;
        min-height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #video {
        display: block;
        width: 100%;
        height: auto;
        max-height: 600px;
        object-fit: contain;
        background: #000;
        transition: transform 0.3s ease;
    }

    #video.mirrored {
        transform: scaleX(-1);
    }

    .scanner-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 200px;
        height: 200px;
        border: 3px solid rgba(201, 162, 39, 0.8);
        border-radius: 12px;
        pointer-events: none;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.3);
    }

    .scanner-status {
        position: absolute;
        bottom: 16px;
        left: 50%;
        transform: translateX(-50%);
        padding: 8px 16px;
        background: rgba(0,0,0,.7);
        color: white;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .scanner-status {
        text-align: center;
        padding: 12px;
        background: rgba(0,0,0,.5);
        color: white;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .result {
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: none;
    }

    .result.show {
        display: block;
    }

    .result-ok {
        background: rgba(43,212,125,.1);
        border: 1px solid rgba(43,212,125,.35);
        color: var(--text);
    }

    .result-bad {
        background: rgba(255,77,90,.1);
        border: 1px solid rgba(255,77,90,.35);
        color: var(--text);
    }

    .result-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 8px;
    }

    .result-icon {
        font-size: 1.4rem;
    }

    .result-message {
        margin: 0 0 8px 0;
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .result-details {
        font-size: 0.88rem;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid rgba(11,11,15,.14);
    }

    html[data-theme="dark"] .result-details {
        border-top-color: rgba(243,243,246,.14);
    }

    .result-detail-row {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        margin: 4px 0;
    }

    .result-label {
        color: var(--muted);
        font-weight: 600;
    }

    .result-value {
        font-weight: 700;
        text-align: right;
    }

    button.reset-btn {
        width: 100%;
        padding: 12px;
        border: none;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        cursor: pointer;
        background: var(--gold);
        color: white;
        text-transform: uppercase;
        transition: background 0.2s;
    }

    button.reset-btn:hover {
        background: #b8931f;
    }

    .instructions {
        background: rgba(201,162,39,.08);
        border: 1px solid rgba(201,162,39,.35);
        padding: 16px;
        border-radius: 12px;
        font-size: 0.9rem;
        line-height: 1.6;
        margin-bottom: 16px;
    }

    .instructions strong {
        display: block;
        margin-bottom: 8px;
        font-weight: 700;
    }

    .instructions ul {
        margin: 8px 0;
        padding-left: 20px;
    }

    .instructions li {
        margin: 4px 0;
    }

    .error-banner {
        background: rgba(255,77,90,.1);
        border: 1px solid rgba(255,77,90,.35);
        padding: 12px;
        border-radius: 8px;
        color: var(--text);
        font-size: 0.9rem;
        margin-bottom: 16px;
        display: none;
    }

    .error-banner.show {
        display: block;
    }
    
    .food-items-list {
        background: var(--surface2);
        border-radius: 8px;
        padding: 12px;
        margin-top: 12px;
    }
    
    .food-item {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid var(--border);
    }
    
    .food-item:last-child {
        border-bottom: none;
    }
    
    .food-item-name {
        font-weight: 600;
    }
    
    .food-item-qty {
        color: var(--gold);
        font-weight: 700;
    }
    
    .manual-input-section {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }
    
    .manual-input-section h4 {
        margin-bottom: 12px;
        font-size: 0.95rem;
        color: var(--muted);
    }
</style>

<div id="food-scanner-container">
<main class="bg-starfield">
  <div class="container py-5" style="max-width: 800px;">

    <div class="text-center mx-auto mb-4" style="max-width: 760px;">
      <h1 class="h-serif mb-3" style="font-size: clamp(32px, 4vw, 48px); font-weight: 300; line-height: 1.05;">
        Essensausgabe
      </h1>
      <p class="text-muted" style="font-size: 1.05rem; line-height: 1.7;">
        Willkommen, <strong><?= e($helperName) ?></strong> 
        <span class="mx-2">·</span>
        <a href="/food/food_helper_logout.php" class="text-danger" style="text-decoration: none; font-weight: 600;">Abmelden</a>
      </p>
    </div>

    <div class="card">
      <div class="card-body p-4">
        <div class="alert alert-warning mb-4">
          <strong>Anleitung:</strong>
          <ul class="mb-0 mt-2">
            <li>Kamera aktivieren</li>
            <li>QR-Code vom Essens-Bon in Kamera halten</li>
            <li>Bestellung wird automatisch als eingelöst markiert</li>
            <li>Ergebnis mit Bestelldetails wird angezeigt</li>
          </ul>
        </div>

        <div id="cameraError" class="error-banner">
          <strong>Kamerazugriff verweigert</strong><br>
          Bitte geben Sie der Webseite Zugriff auf Ihre Kamera.
        </div>

        <div class="d-flex gap-2 mb-3">
          <button onclick="toggleCamera()" class="btn btn-save" style="flex: 1;">
            <span id="cameraToggle">Kamera starten</span>
          </button>
          <button onclick="toggleMirror()" id="mirrorBtn" class="btn btn-outline-secondary" style="display: none;" title="Kamera spiegeln">
            ↻
          </button>
        </div>

        <div id="scanner" class="scanner-container" style="display: none;">
          <video id="video" autoplay playsinline></video>
          <div class="scanner-overlay"></div>
          <div id="scannerStatus" class="scanner-status">Positioniere QR-Code im Rahmen</div>
        </div>

        <div id="result" class="result">
          <div class="result-header">
            <span class="result-icon" id="resultIcon"></span>
            <span id="resultStatus"></span>
          </div>
          <p class="result-message" id="resultMessage"></p>
          <div class="result-details" id="resultDetails"></div>
          <button class="reset-btn" onclick="resetScanner()">Neuer Scan</button>
        </div>
        
        <!-- Manuelle Eingabe -->
        <div class="manual-input-section">
          <h4>Manuelle Eingabe (falls QR nicht lesbar)</h4>
          <form id="manualForm" onsubmit="return handleManualSubmit(event)">
            <div class="input-group">
              <input 
                type="text" 
                id="manualOrderId" 
                class="form-control" 
                placeholder="Bestellnummer (z.B. FOOD001)"
                pattern="FOOD\d{3,}"
              >
              <button type="submit" class="btn btn-save">Einlösen</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
    const ZXing = window.ZXing;
    let codeReader = null;
    let cameraActive = false;
    let isMirrored = false;
    let isScanning = false;

    async function toggleCamera() {
        if (cameraActive) {
            stopCamera();
        } else {
            startCamera();
        }
    }

    async function startCamera() {
        const element = document.getElementById('scanner');
        const toggleBtn = document.getElementById('cameraToggle');
        const errorBanner = document.getElementById('cameraError');
        const statusDiv = document.getElementById('scannerStatus');

        try {
            // Verstecke alte Fehler
            errorBanner.classList.remove('show');
            
            // Zeige Scanner-Bereich
            element.style.display = 'block';
            statusDiv.textContent = 'Kamera wird initialisiert...';
            
            // Erstelle ZXing Reader
            codeReader = new ZXing.BrowserQRCodeReader();
            
            // Hole verfügbare Kameras
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoInputDevices = devices.filter(device => device.kind === 'videoinput');
            
            if (videoInputDevices.length === 0) {
                errorBanner.classList.add('show');
                errorBanner.innerHTML = '<strong>Keine Kamera gefunden</strong><br>Bitte stellen Sie sicher, dass eine Kamera angeschlossen ist.';
                element.style.display = 'none';
                return;
            }

            // Bevorzuge Rückkamera wenn verfügbar
            let selectedDeviceId = videoInputDevices[0].deviceId;
            for (const device of videoInputDevices) {
                const label = device.label.toLowerCase();
                if (label.includes('back') || 
                    label.includes('rear') ||
                    label.includes('environment') ||
                    label.includes('rück')) {
                    selectedDeviceId = device.deviceId;
                    break;
                }
            }
            
            toggleBtn.textContent = 'Kamera stoppen';
            cameraActive = true;
            statusDiv.textContent = 'Positioniere QR-Code im Rahmen';
            document.getElementById('mirrorBtn').style.display = 'block';

            // Starte kontinuierliches Scanning
            await codeReader.decodeFromVideoDevice(selectedDeviceId, 'video', (result, err) => {
                if (result && !isScanning) {
                    isScanning = true;
                    const text = result.getText();
                    
                    // Versuche URL-Format zu parsen: /food_bon/verify.php?order_id=...&sig=...
                    try {
                        const url = new URL(text);
                        const orderId = url.searchParams.get('order_id');
                        const sig = url.searchParams.get('sig');
                        
                        if (orderId && sig) {
                            statusDiv.textContent = 'QR-Code wird verarbeitet...';
                            handleFoodBonScan(orderId, sig);
                            return;
                        }
                    } catch (e) {
                        // Nicht als URL parsebar
                    }
                    
                    // Fallback: Token-basiertes Format
                    if (text && text.length > 10) {
                        statusDiv.textContent = 'QR-Code wird verarbeitet...';
                        handleTokenScan(text);
                        return;
                    }
                    
                    // Ungültiges Format
                    statusDiv.textContent = 'Ungültiges QR-Code Format';
                    console.warn('Unbekanntes QR-Format:', text);
                    isScanning = false;
                }
                if (err && err.name !== 'NotFoundException') {
                    // Stiller Fehler
                }
            });

        } catch (err) {
            errorBanner.classList.add('show');
            errorBanner.innerHTML = '<strong>Kamerazugriff verweigert</strong><br>' + 
                'Fehler: ' + (err.message || 'Unbekannt') + 
                '<br>Bitte geben Sie der Webseite Zugriff auf Ihre Kamera in den Browser-Einstellungen.';
            toggleBtn.textContent = 'Kamera starten';
            element.style.display = 'none';
            cameraActive = false;
        }
    }

    function stopCamera() {
        if (codeReader) {
            codeReader.reset();
            codeReader = null;
        }
        document.getElementById('scanner').style.display = 'none';
        document.getElementById('cameraToggle').textContent = 'Kamera starten';
        document.getElementById('mirrorBtn').style.display = 'none';
        cameraActive = false;
    }

    function toggleMirror() {
        const video = document.getElementById('video');
        isMirrored = !isMirrored;
        if (isMirrored) {
            video.classList.add('mirrored');
        } else {
            video.classList.remove('mirrored');
        }
    }

    async function handleFoodBonScan(orderId, sig) {
        // Stoppe Kamera sofort nach Scan
        stopCamera();
        
        // Sende an Redeem-Endpoint mit order_id
        await redeemOrder({ order_id: orderId });
    }
    
    async function handleTokenScan(token) {
        stopCamera();
        await redeemOrder({ token: token });
    }
    
    async function handleManualSubmit(event) {
        event.preventDefault();
        const orderId = document.getElementById('manualOrderId').value.trim().toUpperCase();
        
        if (!orderId) {
            alert('Bitte Bestellnummer eingeben');
            return false;
        }
        
        await redeemOrder({ order_id: orderId });
        return false;
    }

    async function redeemOrder(params) {
        const resultDiv = document.getElementById('result');
        const icon = document.getElementById('resultIcon');
        const status = document.getElementById('resultStatus');
        const message = document.getElementById('resultMessage');
        const details = document.getElementById('resultDetails');

        try {
            const formData = new FormData();
            if (params.order_id) {
                formData.append('order_id', params.order_id);
            }
            if (params.token) {
                formData.append('token', params.token);
            }
            
            const response = await fetch('/food/food_helper_redeem.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            resultDiv.classList.add('show');

            if (data.success) {
                resultDiv.className = 'result show result-ok';
                icon.textContent = '✓';
                status.textContent = 'Bestellung eingelöst';
                message.textContent = data.message || 'Bestellung erfolgreich eingelöst';
                
                let detailsHtml = `
                    <div class="result-detail-row">
                        <span class="result-label">Bestellnummer:</span>
                        <span class="result-value">${escapeHtml(data.order_id || '')}</span>
                    </div>
                `;
                
                // Zeige bestellte Artikel
                if (data.items && data.items.length > 0) {
                    detailsHtml += '<div class="food-items-list"><strong>Bestellte Artikel:</strong>';
                    for (const item of data.items) {
                        detailsHtml += `
                            <div class="food-item">
                                <span class="food-item-name">${escapeHtml(item.name || '')}</span>
                                <span class="food-item-qty">× ${item.quantity || 1}</span>
                            </div>
                        `;
                    }
                    detailsHtml += '</div>';
                }
                
                details.innerHTML = detailsHtml;
                
                // Audio-Feedback
                playSuccessSound();
            } else {
                resultDiv.className = 'result show result-bad';
                icon.textContent = '✕';
                status.textContent = 'Fehler';
                message.textContent = data.error || 'Bestellung konnte nicht eingelöst werden';
                
                let detailHtml = '';
                if (data.order_id) {
                    detailHtml += `
                        <div class="result-detail-row">
                            <span class="result-label">Bestellnummer:</span>
                            <span class="result-value">${escapeHtml(data.order_id)}</span>
                        </div>
                    `;
                }
                details.innerHTML = detailHtml || '(Keine Zusatzinformationen)';
                
                // Audio-Feedback
                playErrorSound();
            }
        } catch (err) {
            resultDiv.className = 'result show result-bad';
            icon.textContent = '✕';
            status.textContent = 'Fehler';
            message.textContent = 'Netzwerkfehler oder ungültige Anfrage';
            details.textContent = '';
            playErrorSound();
        } finally {
            isScanning = false;
            document.getElementById('manualOrderId').value = '';
        }
    }

    function resetScanner() {
        document.getElementById('result').classList.remove('show');
        isScanning = false;
        startCamera();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function playSuccessSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
        } catch (e) {}
    }

    function playErrorSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 300;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.2, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        } catch (e) {}
    }
  </script>
</main>
</div>
<?php Layout::footer(); ?>
