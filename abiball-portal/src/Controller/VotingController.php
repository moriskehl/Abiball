<?php
declare(strict_types=1);

/**
 * VotingController - Steuerung des Lehrer-Votings
 */

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Auth/AuthContext.php';
require_once __DIR__ . '/../Service/VotingService.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../Security/Csrf.php';

final class VotingController
{
    public static function show(): void
    {
        Bootstrap::init();
        AuthContext::requireLogin('/login.php');

        // Voting nicht freigeschaltet
        if (!Config::isVotingOpen()) {
            self::renderVotingClosed();
            return;
        }

        $userId = AuthContext::mainId();

        // Lehrer dürfen nicht abstimmen
        if (VotingService::isTeacher($userId)) {
            self::renderTeacherBlocked();
            return;
        }

        $hasVoted = VotingService::hasVoted($userId);
        $canChange = VotingService::canChangeVote();
        
        // Wenn bereits abgestimmt und keine Änderungen mehr erlaubt
        if ($hasVoted && !$canChange) {
            self::renderSuccess();
            return;
        }

        $teachers = VotingService::getTeachers();
        $categories = VotingService::CATEGORIES;
        $previousVotes = $hasVoted ? VotingService::getVotesForUser($userId) : [];
        $isUpdate = $hasVoted && $canChange;

        Layout::header('Lehrer Voting');
        ?>
        <main class="bg-starfield">
            <div class="stars-layer-1"></div>
            <div class="stars-layer-2"></div>
            <div class="stars-layer-3"></div>
            
            <div class="container py-0 px-3 px-sm-4" style="max-width: 1100px;">
                <div class="text-center mx-auto" style="max-width: 820px; padding-top: 18px; padding-bottom: 24px;">
                    <div class="glass-hero-header sm mb-5 animate-fade-up">
                      <h1 class="h-serif mb-3 reveal-text" style="font-size: clamp(36px, 4.5vw, 64px); font-weight: 300; line-height: 1.0;">
                        <span style="font-size: 70%;">Abstimmung</span><br>
                        <span style="font-style: italic;">Lehrer Voting</span>
                      </h1>
                      <p class="text-muted mt-3" style="max-width: 600px; margin: 0 auto; font-size: 1.05rem; line-height: 1.7;">
                        <?= $isUpdate ? 'Du kannst deine Auswahl noch ändern.' : 'Wähle deine Favoriten in jeder Kategorie.' ?>
                      </p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-4 p-md-5">
                        <form method="post" action="/voting/save.php">
                            <?= Csrf::inputField() ?>
                            
                            <?php foreach ($categories as $catKey => $catLabel): ?>
                                <?php $selectedId = $previousVotes[$catKey] ?? ''; ?>
                                <div class="mb-4">
                                    <label class="form-label fw-semibold mb-2"><?= htmlspecialchars($catLabel) ?></label>
                                    <select class="form-select" name="votes[<?= $catKey ?>]" required>
                                        <option value="" <?= $selectedId === '' ? 'selected' : '' ?> disabled>Wähle eine Person...</option>
                                        <?php foreach ($teachers as $id => $name): ?>
                                            <option value="<?= htmlspecialchars($id) ?>" <?= $selectedId === $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>

                            <div class="text-muted small mb-4" style="line-height: 1.6;">
                                <strong>Hinweis:</strong> Deine Wahl ist anonym. Du kannst sie bis 18:00 Uhr am Tag des Abiballs ändern.
                            </div>

                            <button type="submit" class="btn w-100 py-3" style="font-size: 1.05rem; font-weight: 500; border-radius: 10px; background: transparent; border: 2px solid var(--gold, #c9a227); color: var(--gold, #c9a227); transition: all 0.25s ease;">
                                <?= $isUpdate ? 'Auswahl aktualisieren' : 'Abstimmung absenden' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
        <?php
        Layout::footer();
    }

    public static function save(): void
    {
        Bootstrap::init();
        AuthContext::requireLogin('/login.php');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /voting/index.php');
            exit;
        }

        if (!Csrf::validate($_POST['_csrf'] ?? '')) {
            die('Invalid CSRF token');
        }

        // Voting nicht freigeschaltet
        if (!Config::isVotingOpen()) {
            header('Location: /voting/index.php');
            exit;
        }

        $userId = AuthContext::mainId();

        // Lehrer dürfen nicht abstimmen
        if (VotingService::isTeacher($userId)) {
            header('Location: /voting/index.php');
            exit;
        }

        $votes = $_POST['votes'] ?? [];

        if (VotingService::submitVote($userId, $votes)) {
            header('Location: /voting/result.php?success=1');
        } else {
            // Fehler oder schon abgestimmt
            header('Location: /voting/index.php?err=1');
        }
        exit;
    }

    public static function renderSuccess(): void
    {
        Layout::header('Voting Abgeschlossen');
        ?>
        <main class="bg-starfield">
            <div class="stars-layer-1"></div>
            <div class="stars-layer-2"></div>
            <div class="stars-layer-3"></div>
            
            <div class="container py-5 text-center" style="max-width: 600px; min-height: 60vh; display: flex; flex-direction: column; justify-content: center;">
                <div class="text-muted small mb-2" style="letter-spacing:.22em; text-transform:uppercase;">Abstimmung</div>
                <h1 class="h-serif mb-3 reveal-text" style="font-size: clamp(28px, 3.5vw, 40px); font-weight: 300;">Vielen Dank!</h1>
                <p class="text-muted mb-5" style="font-size: 1.1rem;">Deine Stimme wurde gezählt.</p>
                
                <div class="d-grid gap-3 col-md-8 mx-auto">
                    <a href="/voting/result.php" class="btn btn-save btn-shimmer">
                        Ergebnisse ansehen
                    </a>
                    <a href="/dashboard.php" class="btn btn-outline-secondary btn-soft">
                        Zurück zum Dashboard
                    </a>
                </div>
            </div>
        </main>
        <?php
        Layout::footer();
    }

    public static function renderVotingClosed(): void
    {
        Layout::header('Voting - Noch nicht verfügbar');
        ?>
        <main class="bg-starfield">
            <div class="stars-layer-1"></div>
            <div class="stars-layer-2"></div>
            <div class="stars-layer-3"></div>
            
            <div class="container py-5 text-center" style="max-width: 600px; min-height: 60vh; display: flex; flex-direction: column; justify-content: center;">
                <div class="text-muted small mb-2" style="letter-spacing:.22em; text-transform:uppercase;">Abstimmung</div>
                <h1 class="h-serif mb-3 reveal-text" style="font-size: clamp(28px, 3.5vw, 40px); font-weight: 300;">Noch nicht verfügbar</h1>
                <p class="text-muted mb-4" style="font-size: 1.05rem; max-width: 400px; margin: 0 auto;">
                    Das Lehrer-Voting ist aktuell noch nicht freigeschaltet.<br>
                    Sobald die Abstimmung beginnt, informieren wir euch hier.
                </p>
                
                <div class="d-grid gap-3 col-md-8 mx-auto">
                    <a href="/voting/result.php" class="btn btn-outline-secondary btn-soft">
                        Zur Ergebnisseite
                    </a>
                    <a href="/dashboard.php" class="btn btn-link text-muted">
                        Zurück zum Dashboard
                    </a>
                </div>
            </div>
        </main>
        <?php
        Layout::footer();
    }

    public static function renderTeacherBlocked(): void
    {
        Layout::header('Voting - Nicht verfügbar');
        ?>
        <main class="bg-starfield">
            <div class="stars-layer-1"></div>
            <div class="stars-layer-2"></div>
            <div class="stars-layer-3"></div>
            
            <div class="container py-5 text-center" style="max-width: 600px; min-height: 60vh; display: flex; flex-direction: column; justify-content: center;">
                <div class="text-muted small mb-2" style="letter-spacing:.22em; text-transform:uppercase;">Abstimmung</div>
                <h1 class="h-serif mb-3 reveal-text" style="font-size: clamp(28px, 3.5vw, 40px); font-weight: 300;">Nur für Schüler</h1>
                <p class="text-muted mb-4" style="font-size: 1.05rem; max-width: 400px; margin: 0 auto;">
                    Das Lehrer-Voting steht nur für Schülerinnen und Schüler zur Verfügung.<br>
                    Als Lehrkraft können Sie die Ergebnisse am Abend des Abiballs einsehen.
                </p>
                
                <div class="d-grid gap-3 col-md-8 mx-auto">
                    <a href="/voting/result.php" class="btn btn-outline-secondary btn-soft">
                        Ergebnisse ansehen
                    </a>
                    <a href="/dashboard.php" class="btn btn-link text-muted">
                        Zurück zum Dashboard
                    </a>
                </div>
            </div>
        </main>
        <?php
        Layout::footer();
    }

    public static function listResults(): void
    {
        Bootstrap::init();
        // Public access allowed for results page
        // AuthContext::requireLogin('/login.php');

        $resultsVisible = Config::areResultsVisible();
        $results = $resultsVisible ? VotingService::getResults() : [];
        $categories = VotingService::CATEGORIES;

        // Calculate deadline for display
        // Calculate deadline for display (now 18:00 on event day)
        $eventDate = new DateTime(Config::EVENT_DATE);
        $deadline = (clone $eventDate)->setTime(18, 0, 0);
        $eventDateStr = $eventDate->format('d.m.Y');

        Layout::header('Voting Ergebnisse');
        ?>
        <main class="bg-starfield">
            <div class="stars-layer-1"></div>
            <div class="stars-layer-2"></div>
            <div class="stars-layer-3"></div>

            <div class="container py-0 px-3 px-sm-4" style="max-width: 1100px;">
                <div class="text-center mx-auto" style="max-width: 820px; padding-top: 18px; padding-bottom: 24px;">
                    <div class="glass-hero-header sm mb-5 animate-fade-up">
                      <h1 class="h-serif mb-3 reveal-text" style="font-size: clamp(36px, 4.5vw, 64px); font-weight: 300; line-height: 1.0;">
                        <span style="font-size: 70%;">Abstimmung</span><br>
                        <span style="font-style: italic;">Die Ergebnisse</span>
                      </h1>
                      <p class="text-muted mt-3" style="max-width: 600px; margin: 0 auto; font-size: 1.05rem; line-height: 1.7;">
                            <?php if ($resultsVisible): ?>
                                Top 5 Platzierungen pro Kategorie
                            <?php else: ?>
                                Spannung bis zum Schluss!
                            <?php endif; ?>
                      </p>
                    </div>
                </div>

                <?php if (!$resultsVisible): ?>
                    <!-- Coming Soon - Evening Reveal -->
                    <div class="card mb-4" style="border: 2px dashed var(--border); background: transparent;">
                        <div class="card-body p-5 text-center">
                            <div class="mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="var(--gold)" viewBox="0 0 16 16" style="opacity: 0.8;">
                                    <path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/>
                                </svg>
                            </div>
                            <h3 class="h5 mb-2" style="font-weight: 600;">Die große Enthüllung</h3>
                            <p class="text-muted mb-3" style="max-width: 450px; margin: 0 auto;">
                                Die Ergebnisse werden am <strong>Abend des Abiballs</strong> live enthüllt.<br>
                                Sei gespannt, wer in jeder Kategorie gewinnt!
                            </p>
                            <div class="badge bg-dark text-light px-3 py-2 mb-3" style="font-size: 0.9rem;">
                                📅 <?= htmlspecialchars($eventDateStr) ?> ab 18:00 Uhr
                            </div>
                            <div class="mt-3 pt-3 border-top" style="border-color: var(--border) !important;">
                                <p class="text-muted small mb-2">
                                    Du hast noch nicht abgestimmt? Kein Problem!
                                </p>
                                <a href="/voting/index.php" class="btn btn-outline-secondary btn-soft btn-sm">
                                    Jetzt abstimmen
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Actual Results -->
                    <div class="row g-4">
                        <?php $delay = 100; ?>
                        <?php foreach ($results as $key => $catData): ?>
                            <div class="col-md-6">
                                <div class="card h-100 hover-float animate-fade-up" style="animation-delay: <?= $delay ?>ms;">
                                    <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                                        <h3 class="h5 mb-0 text-primary" style="font-weight: 700; color: var(--gold) !important;">
                                            <?= htmlspecialchars($catData['label']) ?>
                                        </h3>
                                    </div>
                                    <div class="card-body px-4 pb-4">
                                        <?php if (empty($catData['rankings'])): ?>
                                            <p class="text-muted small">Noch keine Stimmen.</p>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($catData['rankings'] as $idx => $rank): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-2">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <span class="badge rounded-pill <?= $idx === 0 ? 'bg-warning text-dark' : 'bg-secondary' ?>" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                                                                <?= $idx + 1 ?>
                                                            </span>
                                                            <span class="fw-semibold"><?= htmlspecialchars($rank['name']) ?></span>
                                                        </div>
                                                        <span class="badge bg-light text-dark border"><?= $rank['votes'] ?> Stimmen</span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php $delay += 100; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        <?php
        Layout::footer();
    }
}
