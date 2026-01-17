<?php
declare(strict_types=1);

/**
 * FaqController - Häufig gestellte Fragen (FAQ)
 * 
 * Zeigt kategorisierte FAQs mit Suchfunktion und strukturierten Daten für Google.
 */

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';

final class FaqController
{
    /**
     * Liefert alle FAQ-Kategorien mit Fragen und Antworten.
     * Hier können neue Fragen einfach hinzugefügt werden.
     */
    private static function getFaqData(): array
    {
        return [
            [
                'category' => 'Allgemein',
                'questions' => [
                    [
                        'question' => 'Wofür ist dieses Portal?',
                        'answer' => 'Über dieses Portal verwaltest du die wichtigsten Dinge rund um den Abiball: persönliche Daten, Begleitpersonen, Sitzgruppen-Wünsche, Zahlungsstatus sowie Ticket- und ggf. Essensdokumente (PDF/QR).'
                    ],
                    [
                        'question' => 'Wo finde ich die wichtigsten Funktionen?',
                        'answer' => 'Nach dem Login findest du alles im Dashboard: Ticket, Sitzgruppen-Wünsche, ggf. Essensbestellung sowie Hinweise zu Zahlungen. Viele Seiten sind außerdem über die Navigation erreichbar.'
                    ],
                    [
                        'question' => 'Ich finde eine Information nicht – was tun?',
                        'answer' => 'Nutze oben die Suche in den FAQs. Wenn du weiterhin nicht weiterkommst, melde dich per E-Mail an moris.kehl@gmail.com (Moris Kehl).'
                    ],
                    [
                        'question' => 'An wen kann ich mich bei Änderungen wenden?',
                        'answer' => 'Für Änderungen (z.B. Grammatik/Name, zusätzliche oder weniger Begleitpersonen) bitte an moris.kehl@gmail.com wenden oder Moris Kehl direkt ansprechen.'
                    ],
                    [
                        'question' => 'Gibt es eine allgemeine Sitzplatzordnung?',
                        'answer' => 'Nein. Es gibt keine allgemeine feste Sitzplatzordnung. Die Sitzgruppen dienen dazu, Wünsche anzugeben – nicht, um eine starre Sitzordnung festzulegen.'
                    ],
                    [
                        'question' => 'Muss ich meinen Personalausweis mitbringen?',
                        'answer' => 'Ja. Bitte bringt euren Personalausweis mit, da am Einlass eine Ausweiskontrolle verlangt werden kann.'
                    ],
                ]
            ],
            [
                'category' => 'Login & Ticket',
                'questions' => [
                    [
                        'question' => 'Wie logge ich mich ein?',
                        'answer' => 'Über die Login-Seite mit deinem persönlichen Login-Code. Den Login-Code erhältst du von der Organisation. Wenn du Probleme mit deinem Code hast, melde dich bei Moris Kehl (moris.kehl@gmail.com).'
                    ],
                    [
                        'question' => 'Wo finde ich mein Ticket?',
                        'answer' => 'Nach dem Login im Dashboard. Dort kannst du dein Ticket als PDF anzeigen/herunterladen und den QR-Code für den Einlass vorzeigen.'
                    ],
                    [
                        'question' => 'Kann ich mein Ticket an jemand anderen weitergeben?',
                        'answer' => 'Tickets sind grundsätzlich personenbezogen. Wenn sich etwas ändern muss, melde dich bitte bei Moris Kehl (moris.kehl@gmail.com), damit die Orga das korrekt klären kann.'
                    ],
                    [
                        'question' => 'Was passiert am Einlass?',
                        'answer' => 'Am Einlass wird dein Ticket-QR-Code gescannt. Bitte halte zusätzlich deinen Personalausweis bereit, da dieser verlangt werden kann.'
                    ],
                    [
                        'question' => 'Mein QR-Code lässt sich nicht scannen – was kann ich tun?',
                        'answer' => 'Erhöhe die Display-Helligkeit, zoome nicht in den QR-Code hinein und halte das Handy ruhig. Alternativ das Ticket-PDF neu öffnen. Wenn es weiterhin nicht klappt, Einlasspersonal ansprechen.'
                    ],
                    [
                        'question' => 'Ich sehe im Dashboard etwas Falsches (Name/Fehler) – was tun?',
                        'answer' => 'Bitte kurz eine Mail an moris.kehl@gmail.com senden (Moris Kehl). Änderungen wie Grammatik/Name oder Begleitpersonen werden darüber koordiniert.'
                    ],
                ]
            ],
            [
                'category' => 'Essensbestellung',
                'questions' => [
                    [
                        'question' => 'Wie bestelle ich Essen für den Abiball?',
                        'answer' => 'Die Essensbestellung erfolgt im Portal (im Dashboard bzw. auf der Essensbestellungsseite). Bitte wähle deine gewünschten Artikel aus und erstelle die Bestellung.'
                    ],
                    [
                        'question' => 'Warum muss Essen vorher bestellt und bezahlt werden?',
                        'answer' => 'Wir sind an den Caterer gebunden. Damit die Menge planbar ist, muss das Essen im Voraus bestellt und per Überweisung bezahlt werden. Danke für euer Verständnis.'
                    ],
                    [
                        'question' => 'Wie bezahle ich meine Essensbestellung?',
                        'answer' => 'Nach dem Erstellen der Essensbestellung wird dir der Überweisungs-Hinweis mit Empfänger/IBAN und Verwendungszweck angezeigt. Bitte überweise den Betrag genau mit dem angegebenen Verwendungszweck.'
                    ],
                    [
                        'question' => 'Wann wird meine Essensbestellung freigeschaltet?',
                        'answer' => 'Nach Zahlungseingang wird die Bestellung von der Orga/Admin als bezahlt markiert. Das kann je nach Banklaufzeit 1–2 Werktage dauern.'
                    ],
                    [
                        'question' => 'Wie bekomme ich meinen Essens-Bon?',
                        'answer' => 'Sobald deine Essensbestellung als bezahlt markiert wurde, kannst du den Bon als PDF mit QR-Code herunterladen. Den QR-Code zeigst du bei der Essensausgabe vor.'
                    ],
                    [
                        'question' => 'Kann ich meine Essensbestellung stornieren?',
                        'answer' => 'Offene (noch nicht bezahlte) Essensbestellungen können im Portal storniert werden. Wenn bereits bezahlt wurde oder es Sonderfälle gibt, melde dich bitte bei Moris Kehl (moris.kehl@gmail.com).'
                    ],
                    [
                        'question' => 'Was passiert bei der Essensausgabe?',
                        'answer' => 'Bei der Essensausgabe wird der QR-Code vom Essens-Bon gescannt und die Bestellung als eingelöst markiert. Bitte halte den Bon digital oder ausgedruckt bereit.'
                    ],
                ]
            ],
            [
                'category' => 'Sitzgruppen (Wünsche)',
                'questions' => [
                    [
                        'question' => 'Gibt es eine feste Sitzplatzordnung?',
                        'answer' => 'Nein. Es gibt keine allgemeine feste Sitzplatzordnung. Die Sitzgruppen dienen dazu, Wünsche zu äußern – nicht, um eine feste Sitzordnung zu garantieren.'
                    ],
                    [
                        'question' => 'Wofür sind Sitzgruppen dann gedacht?',
                        'answer' => 'Sitzgruppen sind dafür da, besondere Sitz-Wünsche zu übermitteln (z.B. geschiedene Elternteile trennen, weiter weg von Lautsprechern sitzen, bestimmte Konstellationen berücksichtigen).'
                    ],
                    [
                        'question' => 'Kann ich meinen Sitzgruppen-Wunsch später ändern?',
                        'answer' => 'Wenn du etwas ändern möchtest (z.B. neue Begleitperson oder geänderter Wunsch), melde dich bitte bei Moris Kehl (moris.kehl@gmail.com).'
                    ],
                    [
                        'question' => 'Werden alle Wünsche garantiert umgesetzt?',
                        'answer' => 'Wir versuchen möglichst viele Wünsche zu berücksichtigen, können aber nicht alles garantieren. Die Sitzgruppen sind ein Wunsch-System, keine verbindliche Platzreservierung.'
                    ],
                    [
                        'question' => 'Was ist ein sinnvoller Sitz-Wunsch?',
                        'answer' => 'Sinnvoll sind konkrete Hinweise, die bei der Planung helfen: „Eltern getrennt“, „weiter weg von Boxen“, „medizinischer Bedarf“, „Rollstuhl/Barrierefreiheit“ usw.'
                    ],
                ]
            ],
            [
                'category' => 'Veranstaltung',
                'questions' => [
                    [
                        'question' => 'Wann und wo findet der Abiball statt?',
                        'answer' => 'Ort/Datum findest du auf der Location-Seite und in den Infos im Portal. Bitte prüfe vor dem Event nochmal die aktuellen Hinweise.'
                    ],
                    [
                        'question' => 'Gibt es einen Dresscode?',
                        'answer' => 'In der Regel ist festliche Kleidung erwünscht. Falls die Orga konkrete Vorgaben macht, findest du diese im Portal oder erhältst sie über die üblichen Kommunikationswege.'
                    ],
                    [
                        'question' => 'Wie läuft der Abend ab?',
                        'answer' => 'Der genaue Ablauf wird von der Orga kommuniziert. Plane bitte genügend Zeit für Einlass/Scan ein und halte Ticket + Ausweis bereit.'
                    ],
                    [
                        'question' => 'Gibt es Parkmöglichkeiten?',
                        'answer' => 'Hinweise zu Anfahrt und Parken findest du auf der Location-Seite. Bitte beachte ggf. Beschilderung vor Ort.'
                    ],
                    [
                        'question' => 'Muss ich mein Ticket ausdrucken?',
                        'answer' => 'Nein, in der Regel reicht es digital (PDF/QR-Code) auf dem Handy. Wenn du auf Nummer sicher gehen willst, kannst du es zusätzlich ausdrucken.'
                    ],
                    [
                        'question' => 'Was soll ich am Abend dabei haben?',
                        'answer' => 'Ticket (digital oder ausgedruckt) und unbedingt Personalausweis. Falls du Essen vorbestellt hast: den Essens-Bon (QR-Code).'
                    ],
                ]
            ],
            [
                'category' => 'Technische Fragen',
                'questions' => [
                    [
                        'question' => 'Ich kann mich nicht einloggen – was sind die häufigsten Ursachen?',
                        'answer' => 'Meist liegt es an Tippfehlern im Login-Code oder an Copy/Paste mit Leerzeichen. Bitte gib den Code genau ein. Wenn es weiterhin nicht klappt, melde dich bei moris.kehl@gmail.com.'
                    ],
                    [
                        'question' => 'Die Seite lädt nicht richtig oder sieht komisch aus – was kann ich tun?',
                        'answer' => 'Bitte Seite neu laden, Cache leeren oder einen anderen Browser probieren. Auf dem Handy hilft oft auch ein Neustart des Browsers.'
                    ],
                    [
                        'question' => 'Warum sehe ich manche Seiten nicht?',
                        'answer' => 'Einige Bereiche sind nur nach Login erreichbar. Wenn du ausgeloggt wurdest, logge dich einfach erneut ein.'
                    ],
                    [
                        'question' => 'Warum werde ich automatisch ausgeloggt?',
                        'answer' => 'Aus Sicherheitsgründen läuft eine Sitzung nach einer gewissen Zeit ab. Das ist normal – logge dich dann einfach erneut ein.'
                    ],
                    [
                        'question' => 'An wen wende ich mich bei Problemen?',
                        'answer' => 'Bei Problemen oder Änderungswünschen: moris.kehl@gmail.com (Moris Kehl).'
                    ],
                ]
            ],
        ];
    }

    /**
     * Zeigt die FAQ-Seite mit Suchfunktion und Accordion-Darstellung.
     */
    public static function show(): void
    {
        Bootstrap::init();

        $faqData = self::getFaqData();

        Layout::header('FAQ – Häufige Fragen', 'Häufig gestellte Fragen zum Abiball 2026 BSZ Leonberg. Finde Antworten zu Tickets, Zahlung, Sitzplätzen und mehr.');
        
        // Strukturierte Daten für Google Rich Results
        $allFaqs = [];
        foreach ($faqData as $category) {
            foreach ($category['questions'] as $q) {
                $allFaqs[] = $q;
            }
        }
        Layout::faqStructuredData($allFaqs);
        Layout::breadcrumbStructuredData(['Startseite' => '/', 'FAQ' => '/faq.php']);
        
        self::renderView($faqData);
        Layout::footer();
    }

    /**
     * Rendert die FAQ-Ansicht mit Kategorien und Suchfunktion.
     */
    private static function renderView(array $faqData): void
    {
        ?>
<style>
.faq-search-container {
    max-width: 600px;
    margin: 0 auto 2rem auto;
}

.faq-search-input {
    width: 100%;
    padding: 1rem 1.25rem 1rem 3rem;
    font-size: 1rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
    color: var(--text);
    transition: all 0.2s;
}

.faq-search-input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.15);
}

.faq-search-wrapper {
    position: relative;
}

.faq-search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    pointer-events: none;
}

.faq-category {
    margin-bottom: 2rem;
}

.faq-category-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid rgba(201, 162, 39, 0.3);
    color: var(--gold);
}

.faq-accordion .accordion-item {
    border: 1px solid var(--border);
    border-radius: 12px !important;
    margin-bottom: 0.75rem;
    overflow: hidden;
    background: var(--surface);
}

.faq-accordion .accordion-button {
    padding: 1rem 1.25rem;
    font-weight: 500;
    background: var(--surface);
    color: var(--text);
    border: none;
    box-shadow: none !important;
}

.faq-accordion .accordion-button:not(.collapsed) {
    background: rgba(201, 162, 39, 0.08);
    color: var(--gold);
}

.faq-accordion .accordion-button::after {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23c9a227'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
}

.faq-accordion .accordion-body {
    padding: 1rem 1.25rem;
    line-height: 1.7;
    color: var(--text);
    background: var(--surface);
    border-top: 1px solid var(--border);
}

.faq-no-results {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--muted);
}

.faq-no-results-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.faq-highlight {
    background: rgba(201, 162, 39, 0.25);
    padding: 0 2px;
    border-radius: 2px;
}

.faq-contact-box {
    background: rgba(201, 162, 39, 0.08);
    border: 1px solid rgba(201, 162, 39, 0.25);
    border-radius: 14px;
    padding: 1.5rem;
    text-align: center;
    margin-top: 2rem;
}

.faq-contact-box h4 {
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.faq-contact-box a {
    color: var(--gold);
    font-weight: 600;
    text-decoration: none;
}

.faq-contact-box a:hover {
    text-decoration: underline;
}
</style>

<main class="bg-starfield">
  <div class="container py-5" style="max-width: 900px;">

    <div class="text-center mb-5">
      <h1 class="h-serif mb-3" style="font-size: clamp(36px, 4.5vw, 64px); font-weight: 300; line-height: 1.0;">
        <span style="font-size: 70%;">Hilfe & Support</span><br>
        <span style="font-style: italic;">Häufige Fragen</span>
      </h1>
      <p class="text-muted mt-3" style="max-width: 600px; margin: 0 auto; font-size: 1.05rem; line-height: 1.7;">
        Hier findest du Antworten auf die häufigsten Fragen rund um den Abiball 2026.
      </p>
    </div>

    <!-- Suchfeld -->
    <div class="faq-search-container">
      <div class="faq-search-wrapper">
        <svg class="faq-search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.3-4.3"></path>
        </svg>
        <input 
          type="text" 
          id="faqSearch" 
          class="faq-search-input" 
          placeholder="Suche nach Fragen..."
          autocomplete="off"
        >
      </div>
    </div>

    <!-- FAQ Kategorien -->
    <div id="faqContainer">
      <?php foreach ($faqData as $categoryIndex => $category): ?>
        <div class="faq-category" data-category="<?= e($category['category']) ?>">
          <h2 class="faq-category-title"><?= e($category['category']) ?></h2>
          
          <div class="accordion faq-accordion" id="accordion<?= $categoryIndex ?>">
            <?php foreach ($category['questions'] as $questionIndex => $item): ?>
              <?php $itemId = "faq-{$categoryIndex}-{$questionIndex}"; ?>
              <div class="accordion-item faq-item" data-question="<?= e(strtolower($item['question'])) ?>" data-answer="<?= e(strtolower($item['answer'])) ?>">
                <h3 class="accordion-header">
                  <button 
                    class="accordion-button collapsed" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#<?= $itemId ?>" 
                    aria-expanded="false" 
                    aria-controls="<?= $itemId ?>"
                  >
                    <?= e($item['question']) ?>
                  </button>
                </h3>
                <div id="<?= $itemId ?>" class="accordion-collapse collapse" data-bs-parent="#accordion<?= $categoryIndex ?>">
                  <div class="accordion-body">
                    <?= e($item['answer']) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Keine Ergebnisse -->
    <div id="noResults" class="faq-no-results" style="display: none;">
      <div class="faq-no-results-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.3-4.3"></path>
          <path d="M8 8l6 6"></path>
          <path d="M14 8l-6 6"></path>
        </svg>
      </div>
      <h4>Keine Ergebnisse gefunden</h4>
      <p>Versuche es mit anderen Suchbegriffen oder kontaktiere uns direkt.</p>
    </div>

    <!-- Kontakt-Box -->
    <div class="faq-contact-box">
      <h4>Noch Fragen?</h4>
      <p class="text-muted mb-2">
        Falls du hier keine Antwort gefunden hast, kontaktiere uns gerne per E-Mail:
      </p>
      <a href="mailto:moris.kehl@gmail.com">moris.kehl@gmail.com</a>
    </div>

  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('faqSearch');
    const faqContainer = document.getElementById('faqContainer');
    const noResults = document.getElementById('noResults');
    const categories = document.querySelectorAll('.faq-category');
    const items = document.querySelectorAll('.faq-item');

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        let hasResults = false;

        if (query === '') {
            // Zeige alle
            categories.forEach(cat => cat.style.display = '');
            items.forEach(item => item.style.display = '');
            noResults.style.display = 'none';
            return;
        }

        categories.forEach(category => {
            const categoryName = category.dataset.category.toLowerCase();
            const categoryItems = category.querySelectorAll('.faq-item');
            let categoryHasMatch = false;

            // Prüfe ob Kategoriename passt
            if (categoryName.includes(query)) {
                categoryHasMatch = true;
                categoryItems.forEach(item => item.style.display = '');
            } else {
                // Prüfe einzelne Fragen
                categoryItems.forEach(item => {
                    const question = item.dataset.question;
                    const answer = item.dataset.answer;

                    if (question.includes(query) || answer.includes(query)) {
                        item.style.display = '';
                        categoryHasMatch = true;
                    } else {
                        item.style.display = 'none';
                    }
                });
            }

            category.style.display = categoryHasMatch ? '' : 'none';
            if (categoryHasMatch) hasResults = true;
        });

        noResults.style.display = hasResults ? 'none' : 'block';
    });
});
</script>
        <?php
    }
}
