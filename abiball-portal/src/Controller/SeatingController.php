<?php
declare(strict_types=1);

// src/Controller/SeatingController.php
require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Security/Csrf.php';
require_once __DIR__ . '/../Auth/AuthContext.php';
require_once __DIR__ . '/../Service/ParticipantService.php';
require_once __DIR__ . '/../Service/SeatingService.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';

final class SeatingController
{
    public static function show(): void
    {
        Bootstrap::init();
        AuthContext::requireLogin('/login.php');

        $mainId = AuthContext::mainId();
        if ($mainId === '') {
            header('Location: /login.php');
            exit;
        }

        $group = ParticipantService::getMainAndCompanions($mainId);
        $main = $group['main'] ?? null;
        $companions = $group['companions'] ?? [];

        $people = [];
        if (is_array($main) && !empty($main['id'])) {
            $people[] = ['id' => (string)$main['id'], 'name' => (string)($main['name'] ?? '')];
        }
        if (is_array($companions)) {
            foreach ($companions as $c) {
                if (!is_array($c) || empty($c['id'])) continue;
                $people[] = ['id' => (string)$c['id'], 'name' => (string)($c['name'] ?? '')];
            }
        }

        $byId = [];
        foreach ($people as $p) {
            $byId[$p['id']] = $p['name'];
        }

        $SG1 = 'SG1'; $SG2 = 'SG2'; $SG3 = 'SG3';

        $seating = SeatingService::load($mainId);
        $groups = $seating['groups'] ?? [];
        if (!is_array($groups)) $groups = [];
        $groupNotes = $seating['group_notes'] ?? [];
        if (!is_array($groupNotes)) $groupNotes = [];

        $hasSG2 = isset($groups[$SG2]) && is_array($groups[$SG2]);
        $hasSG3 = isset($groups[$SG3]) && is_array($groups[$SG3]);

        $allowed = [];
        foreach ($people as $p) $allowed[$p['id']] = true;

        $g2Members = [];
        if ($hasSG2 && isset($groups[$SG2]['members']) && is_array($groups[$SG2]['members'])) {
            foreach ($groups[$SG2]['members'] as $id) {
                $id = trim((string)$id);
                if ($id !== '' && isset($allowed[$id])) $g2Members[] = $id;
            }
        }

        $g3Members = [];
        if ($hasSG3 && isset($groups[$SG3]['members']) && is_array($groups[$SG3]['members'])) {
            foreach ($groups[$SG3]['members'] as $id) {
                $id = trim((string)$id);
                if ($id !== '' && isset($allowed[$id])) $g3Members[] = $id;
            }
        }

        // Unique: SG2, dann SG3, Rest -> SG1
        $seen = [];
        $g2Clean = [];
        foreach ($g2Members as $id) {
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $g2Clean[] = $id;
        }

        $g3Clean = [];
        foreach ($g3Members as $id) {
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $g3Clean[] = $id;
        }

        $g1Clean = [];
        foreach ($people as $p) {
            $id = $p['id'];
            if (!isset($seen[$id])) $g1Clean[] = $id;
        }

        Layout::header('Abiball – Sitzgruppen');
        ?>
        <main class="bg-starfield">
          <div class="container py-4" style="max-width:1100px;">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
              <div>
                <div class="text-muted small" style="letter-spacing:.22em; text-transform:uppercase;">Sitzgruppen</div>
                <h1 class="h-serif mb-1" style="font-size: clamp(28px, 3.5vw, 40px); font-weight: 300; line-height: 1.1;">
                  Gruppen verwalten
                </h1>
                <div class="text-muted" style="font-size:.95rem; line-height:1.6; max-width: 68ch;">
                  Personen zwischen Gruppen verschieben. Änderungen werden erst nach „Speichern“ übernommen.
                </div>
              </div>

                <div class="d-flex gap-2 flex-wrap">
                  <button id="addGroupBtn" class="btn btn-outline-secondary btn-soft" type="button">
                    Gruppe hinzufügen
                  </button>

                  <button id="removeGroupBtn" class="btn btn-outline-secondary btn-soft" type="button" style="display:none;">
                    Gruppe entfernen
                  </button>

                  <a class="btn btn-outline-secondary btn-soft" href="/dashboard.php">
                    Zurück
                  </a>
                </div>

            </div>

            <style>
              .groups-grid{ display:grid; gap:1rem; align-items:start; }
              @media (min-width: 992px){
                .groups-grid.groups--1{ grid-template-columns: 1fr 1fr; }
                .groups-grid.groups--2{ grid-template-columns: repeat(3, 1fr); }
                .groups-grid.groups--3{ grid-template-columns: repeat(3, 1fr); }
                .group-wrap{ min-width:0; }
              }
              @media (max-width: 991.98px){ .groups-grid{ grid-template-columns: 1fr; } }

              .group-add-tile{ width:100%; text-align:left; background:transparent; border:1px dashed var(--border);
                border-radius:var(--radius); box-shadow:none; cursor:pointer;
                transition: transform var(--theme-transition), border-color var(--theme-transition), background-color var(--theme-transition);
              }
              .group-add-tile:hover{ transform: translateY(-1px); background: var(--surface-2); border-color: rgba(201,162,39,.38); }
              .group-add-plus{ width:58px; height:58px; border-radius:16px; display:grid; place-items:center; font-size:34px;
                font-weight:700; line-height:1; background: rgba(201,162,39,.12); border:1px solid rgba(201,162,39,.28); color: var(--primary);
                margin-bottom:.75rem;
              }
              .drop-placeholder{ border-radius:12px; border:1px dashed rgba(201,162,39,.35); background: rgba(201,162,39,.08); margin:.35rem 0; }
              .person-item{ cursor:grab; }
              @media (max-width: 991.98px){ .person-item{ cursor:default; } }

              .btn-save-strong{ border-radius:12px !important; background: linear-gradient(180deg, var(--gold-2), var(--gold));
                border:1px solid rgba(0,0,0,.12); color:#0b0b0f !important; box-shadow:0 14px 34px rgba(201,162,39,.22);
                padding:.70rem 1.15rem; font-weight:700;
              }
              .btn-save-strong:hover{ filter: brightness(1.02); }
              .btn-save-strong:focus{ box-shadow: var(--focus), 0 14px 34px rgba(201,162,39,.22); }
            </style>

            <div id="groups" class="groups-grid">
              <!-- SG1 -->
              <section class="group-wrap" data-wrap-id="<?= e($SG1) ?>">
                <div class="card group-card" data-group-id="<?= e($SG1) ?>">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                      <div class="seating-column-title group-name-text">Sitzgruppe 1</div>
                      <span class="badge text-bg-secondary group-count">0</span>
                    </div>

                    <div class="mt-3">
                      <label class="form-label mb-1">Info zur Sitzgruppe</label>
                      <textarea class="form-control form-control-sm group-note" rows="2" data-group-id="<?= e($SG1) ?>"
                        placeholder="z.B. Tischwunsch …"><?= e((string)($groupNotes[$SG1] ?? '')) ?></textarea>
                    </div>

                    <div class="group-tools mt-3">
                      <input class="form-control form-control-sm person-search"
                        placeholder="Person suchen (Name oder ID) …"
                        autocomplete="off"
                        data-group-id="<?= e($SG1) ?>">
                      <div class="text-muted" style="font-size:.85rem;">Auswahl/Enter → Person wird in Sitzgruppe 1 verschoben</div>
                    </div>

                    <div class="drop-label mt-2">Personen</div>
                    <div class="dropzone" data-dropzone="group" data-group-id="<?= e($SG1) ?>">
                      <div class="list-group list-group-flush">
                        <?php foreach ($g1Clean as $pid): ?>
                          <div class="list-group-item person-item" draggable="true"
                            data-person-id="<?= e($pid) ?>" data-person-name="<?= e($byId[$pid] ?? '') ?>">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                              <div class="fw-semibold"><?= e($byId[$pid] ?? '') ?></div>
                              <span class="badge text-bg-secondary"><?= e($pid) ?></span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </section>

              <!-- SG2 -->
              <section class="group-wrap" id="wrapSG2" data-wrap-id="<?= e($SG2) ?>" style="<?= $hasSG2 ? '' : 'display:none;' ?>">
                <div class="card group-card" data-group-id="<?= e($SG2) ?>">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                      <div class="seating-column-title group-name-text">Sitzgruppe 2</div>
                      <span class="badge text-bg-secondary group-count">0</span>
                    </div>

                    <div class="mt-3">
                      <label class="form-label mb-1">Info zur Sitzgruppe</label>
                      <textarea class="form-control form-control-sm group-note" rows="2" data-group-id="<?= e($SG2) ?>"
                        placeholder="z.B. Tischwunsch …"><?= e((string)($groupNotes[$SG2] ?? '')) ?></textarea>
                    </div>

                    <div class="group-tools mt-3">
                      <input class="form-control form-control-sm person-search"
                        placeholder="Person suchen (Name oder ID) …"
                        autocomplete="off"
                        data-group-id="<?= e($SG2) ?>">
                      <div class="text-muted" style="font-size:.85rem;">Auswahl/Enter → Person wird in Sitzgruppe 2 verschoben</div>
                    </div>

                    <div class="drop-label mt-2">Personen</div>
                    <div class="dropzone" data-dropzone="group" data-group-id="<?= e($SG2) ?>">
                      <div class="list-group list-group-flush">
                        <?php foreach ($g2Clean as $pid): ?>
                          <div class="list-group-item person-item" draggable="true"
                            data-person-id="<?= e($pid) ?>" data-person-name="<?= e($byId[$pid] ?? '') ?>">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                              <div class="fw-semibold"><?= e($byId[$pid] ?? '') ?></div>
                              <span class="badge text-bg-secondary"><?= e($pid) ?></span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <div class="text-muted mt-2" style="font-size:.85rem;">Entfernen der Gruppe verschiebt alle wieder nach Sitzgruppe 1.</div>
                  </div>
                </div>
              </section>

              <!-- SG3 -->
              <section class="group-wrap" id="wrapSG3" data-wrap-id="<?= e($SG3) ?>" style="<?= $hasSG3 ? '' : 'display:none;' ?>">
                <div class="card group-card" data-group-id="<?= e($SG3) ?>">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                      <div class="seating-column-title group-name-text">Sitzgruppe 3</div>
                      <span class="badge text-bg-secondary group-count">0</span>
                    </div>

                    <div class="mt-3">
                      <label class="form-label mb-1">Info zur Sitzgruppe</label>
                      <textarea class="form-control form-control-sm group-note" rows="2" data-group-id="<?= e($SG3) ?>"
                        placeholder="z.B. Tischwunsch …"><?= e((string)($groupNotes[$SG3] ?? '')) ?></textarea>
                    </div>

                    <div class="group-tools mt-3">
                      <input class="form-control form-control-sm person-search"
                        placeholder="Person suchen (Name oder ID) …"
                        autocomplete="off"
                        data-group-id="<?= e($SG3) ?>">
                      <div class="text-muted" style="font-size:.85rem;">Auswahl/Enter → Person wird in Sitzgruppe 3 verschoben</div>
                    </div>

                    <div class="drop-label mt-2">Personen</div>
                    <div class="dropzone" data-dropzone="group" data-group-id="<?= e($SG3) ?>">
                      <div class="list-group list-group-flush">
                        <?php foreach ($g3Clean as $pid): ?>
                          <div class="list-group-item person-item" draggable="true"
                            data-person-id="<?= e($pid) ?>" data-person-name="<?= e($byId[$pid] ?? '') ?>">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                              <div class="fw-semibold"><?= e($byId[$pid] ?? '') ?></div>
                              <span class="badge text-bg-secondary"><?= e($pid) ?></span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <div class="text-muted mt-2" style="font-size:.85rem;">Entfernen der Gruppe verschiebt alle wieder nach Sitzgruppe 1.</div>
                  </div>
                </div>
              </section>

              <!-- Plus-Kachel -->
              <section class="group-wrap" id="addGroupTileWrap" style="display:none;">
                <button type="button" id="addGroupTile" class="card group-add-tile" aria-label="Gruppe hinzufügen">
                  <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center text-center">
                    <div class="group-add-plus">+</div>
                    <div class="text-muted" style="font-size:.95rem;">Gruppe hinzufügen</div>
                  </div>
                </button>
              </section>
            </div>

            <form class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2"
                  method="post" action="/seating_save.php" id="saveForm">
              <?= Csrf::inputField() ?>
              <input type="hidden" name="payload" id="payload">

              <div class="text-muted" style="font-size:.9rem;">Änderungen werden erst nach „Speichern“ übernommen.</div>
              <button class="btn btn-save-strong" type="submit">Speichern</button>
            </form>

          </div>
        </main>

        <script>
        (() => {
          const SG1 = 'SG1', SG2 = 'SG2', SG3 = 'SG3';

          const wrap2 = document.getElementById('wrapSG2');
          const wrap3 = document.getElementById('wrapSG3');

          const btnAdd = document.getElementById('addGroupBtn');
          const btnRemove = document.getElementById('removeGroupBtn');

          const tileWrap = document.getElementById('addGroupTileWrap');
          const tileAdd = document.getElementById('addGroupTile');

          const groupsEl = document.getElementById('groups');
          const mqDesktop = window.matchMedia('(min-width: 992px)');
          const mqMobileLike = window.matchMedia('(max-width: 991.98px)');

          function ensureList(zone) {
            let list = zone.querySelector('.list-group');
            if (!list) {
              list = document.createElement('div');
              list.className = 'list-group list-group-flush';
              zone.appendChild(list);
            }
            return list;
          }

          function updateCounts() {
            document.querySelectorAll('.group-card').forEach(card => {
              const count = card.querySelectorAll('.dropzone .person-item').length;
              const badge = card.querySelector('.group-count');
              if (badge) badge.textContent = String(count);
            });
          }

          function visibleGroupCount() {
            let n = 1;
            if (wrap2 && wrap2.style.display !== 'none') n++;
            if (wrap3 && wrap3.style.display !== 'none') n++;
            return n;
          }

          function applyGridMode() {
            const n = visibleGroupCount();
            groupsEl.classList.remove('groups--1','groups--2','groups--3');
            groupsEl.classList.add(`groups--${n}`);
          }

          function refreshControls() {
            const n = visibleGroupCount();
            if (btnAdd) btnAdd.style.display = (n >= 3) ? 'none' : '';
            if (btnRemove) btnRemove.style.display = (n >= 2) ? '' : 'none';
            const showTile = (n < 3);
            if (tileWrap) tileWrap.style.display = showTile ? '' : 'none';
            applyGridMode();
          }

          function addGroup() {
            if (wrap2 && wrap2.style.display === 'none') wrap2.style.display = '';
            else if (wrap3 && wrap3.style.display === 'none') wrap3.style.display = '';
            refreshControls();
            updateCounts();
          }

          function removeLastGroup() {
            const targetWrap = (wrap3 && wrap3.style.display !== 'none')
              ? wrap3
              : ((wrap2 && wrap2.style.display !== 'none') ? wrap2 : null);

            if (!targetWrap) return;

            const sg1Zone = document.querySelector(`.dropzone[data-dropzone="group"][data-group-id="${CSS.escape(SG1)}"]`);
            const sg1List = sg1Zone ? ensureList(sg1Zone) : null;

            if (sg1List) {
              targetWrap.querySelectorAll('.dropzone .person-item').forEach(el => sg1List.appendChild(el));
            }

            targetWrap.style.display = 'none';
            refreshControls();
            updateCounts();
          }

          // Search
          function getAllPeopleElements() {
            return Array.from(document.querySelectorAll('.person-item'));
          }
          function matchesQuery(el, q) {
            const id = (el.dataset.personId || '').toLowerCase();
            const name = (el.dataset.personName || '').toLowerCase();
            return id.includes(q) || name.includes(q);
          }
          function movePersonToGroup(groupId, personId) {
            const zone = document.querySelector(`.dropzone[data-dropzone="group"][data-group-id="${CSS.escape(groupId)}"]`);
            if (!zone) return;
            const person = document.querySelector(`.person-item[data-person-id="${CSS.escape(personId)}"]`);
            if (!person) return;
            ensureList(zone).appendChild(person);
            updateCounts();
          }
          function wireSearchInput(input) {
            let dropdown = null;

            function closeDropdown() {
              if (dropdown) dropdown.remove();
              dropdown = null;
            }

            function openDropdown(items) {
              closeDropdown();
              dropdown = document.createElement('div');
              dropdown.className = 'list-group mt-1';
              dropdown.style.maxHeight = '220px';
              dropdown.style.overflow = 'auto';

              items.slice(0, 8).forEach(el => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action';
                btn.textContent = `${el.dataset.personName} (${el.dataset.personId})`;
                btn.addEventListener('click', () => {
                  movePersonToGroup(input.dataset.groupId, el.dataset.personId);
                  input.value = '';
                  closeDropdown();
                });
                dropdown.appendChild(btn);
              });

              input.parentElement.appendChild(dropdown);
            }

            input.addEventListener('input', () => {
              const q = input.value.trim().toLowerCase();
              if (q.length < 2) { closeDropdown(); return; }
              const candidates = getAllPeopleElements().filter(el => matchesQuery(el, q));
              if (candidates.length === 0) { closeDropdown(); return; }
              openDropdown(candidates);
            });

            input.addEventListener('keydown', (e) => {
              if (e.key === 'Escape') closeDropdown();
              if (e.key === 'Enter') {
                e.preventDefault();
                const q = input.value.trim().toLowerCase();
                if (q.length < 1) return;
                const candidates = getAllPeopleElements().filter(el => matchesQuery(el, q));
                if (candidates.length >= 1) {
                  movePersonToGroup(input.dataset.groupId, candidates[0].dataset.personId);
                  input.value = '';
                  closeDropdown();
                }
              }
            });

            document.addEventListener('click', (e) => {
              if (!dropdown) return;
              if (e.target === input || dropdown.contains(e.target)) return;
              closeDropdown();
            });
          }
          document.querySelectorAll('.person-search').forEach(wireSearchInput);

          // Drag & Drop
          let placeholder = null;
          let dragHeight = 0;

          function isDragEnabled() { return mqDesktop.matches; }

          function makePlaceholder(heightPx) {
            const ph = document.createElement('div');
            ph.className = 'drop-placeholder';
            ph.style.height = `${Math.max(44, heightPx)}px`;
            return ph;
          }
          function removePlaceholder() {
            if (placeholder && placeholder.parentElement) placeholder.parentElement.removeChild(placeholder);
            placeholder = null;
          }
          function setDraggableState() {
            const enabled = isDragEnabled();
            document.querySelectorAll('.person-item').forEach(el => {
              el.setAttribute('draggable', enabled ? 'true' : 'false');
            });
          }
          function wireDrag(el) {
            el.addEventListener('dragstart', (e) => {
              if (!isDragEnabled()) { e.preventDefault(); return; }
              dragHeight = el.getBoundingClientRect().height || 48;

              e.dataTransfer.setData('text/plain', JSON.stringify({
                personId: el.dataset.personId,
                personName: el.dataset.personName
              }));
              e.dataTransfer.effectAllowed = 'move';

              removePlaceholder();
              placeholder = makePlaceholder(dragHeight);
            });

            el.addEventListener('dragend', () => {
              dragHeight = 0;
              removePlaceholder();
              document.querySelectorAll('.dropzone').forEach(z => z.classList.remove('is-over'));
            });
          }
          document.querySelectorAll('.person-item').forEach(wireDrag);

          function getZone(target) {
            if (!isDragEnabled()) return null;
            if (target && target.closest && target.closest('.person-search')) return null;
            return target && target.closest ? target.closest('.dropzone[data-dropzone="group"]') : null;
          }

          function updateZonePlaceholder(zone) {
            if (!placeholder || !zone) return;
            const list = ensureList(zone);
            if (placeholder.parentElement !== list) {
              removePlaceholder();
              placeholder = makePlaceholder(dragHeight || 48);
              list.appendChild(placeholder);
            }
          }

          document.addEventListener('dragover', (e) => {
            const zone = getZone(e.target);
            if (!zone) return;
            e.preventDefault();
            zone.classList.add('is-over');
            updateZonePlaceholder(zone);
          }, true);

          document.addEventListener('dragleave', (e) => {
            const zone = getZone(e.target);
            if (!zone) return;
            zone.classList.remove('is-over');
          }, true);

          document.addEventListener('drop', (e) => {
            const zone = getZone(e.target);
            if (!zone) return;

            e.preventDefault();
            zone.classList.remove('is-over');

            const raw = e.dataTransfer.getData('text/plain');
            if (!raw) { removePlaceholder(); return; }

            let data;
            try { data = JSON.parse(raw); } catch { removePlaceholder(); return; }

            const person = document.querySelector(`.person-item[data-person-id="${CSS.escape(data.personId)}"]`);
            if (!person) { removePlaceholder(); return; }

            const list = ensureList(zone);
            if (placeholder && placeholder.parentElement === list) list.insertBefore(person, placeholder);
            else list.appendChild(person);

            removePlaceholder();
            updateCounts();
          }, true);

          // Save payload
          document.getElementById('saveForm').addEventListener('submit', () => {
            const data = { groups: {}, group_notes: {} };

            const visibleGroupIds = [SG1];
            if (wrap2 && wrap2.style.display !== 'none') visibleGroupIds.push(SG2);
            if (wrap3 && wrap3.style.display !== 'none') visibleGroupIds.push(SG3);

            visibleGroupIds.forEach(gid => {
              const card = document.querySelector(`.group-card[data-group-id="${CSS.escape(gid)}"]`);
              if (!card) return;

              const members = Array.from(card.querySelectorAll('.dropzone .person-item'))
                .map(p => p.dataset.personId);

              data.groups[gid] = {
                name: card.querySelector('.group-name-text')?.textContent?.trim() || gid,
                members
              };
            });

            document.querySelectorAll('.group-note').forEach(t => {
              const gid = t.dataset.groupId;
              if (!gid) return;
              data.group_notes[gid] = (t.value || '').trim();
            });

            document.getElementById('payload').value = JSON.stringify(data);
          });

          if (btnAdd) btnAdd.addEventListener('click', addGroup);
          if (tileAdd) tileAdd.addEventListener('click', addGroup);
          if (btnRemove) btnRemove.addEventListener('click', removeLastGroup);

          function onResizeLike() { setDraggableState(); refreshControls(); }
          mqDesktop.addEventListener?.('change', onResizeLike);
          mqMobileLike.addEventListener?.('change', onResizeLike);
          window.addEventListener('resize', onResizeLike);

          setDraggableState();
          refreshControls();
          updateCounts();
        })();
        </script>

        <?php
        Layout::footer();
    }

    public static function save(): void
    {
        Bootstrap::init();
        AuthContext::requireLogin('/login.php');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: /seating.php');
            exit;
        }

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF ungültig.';
            exit;
        }

        $mainId = AuthContext::mainId();
        if ($mainId === '') {
            header('Location: /login.php');
            exit;
        }

        $payload = (string)($_POST['payload'] ?? '');
        $data = json_decode($payload, true);
        if (!is_array($data) || !isset($data['groups']) || !is_array($data['groups'])) {
            http_response_code(400);
            echo 'Payload ungültig.';
            exit;
        }

        $groupNotes = $data['group_notes'] ?? [];
        if (!is_array($groupNotes)) $groupNotes = [];

        $SG1='SG1'; $SG2='SG2'; $SG3='SG3';

        // Allowed IDs (nur eigene Gruppe)
        $grp = ParticipantService::getMainAndCompanions($mainId);
        $allowed = [];
        if (!empty($grp['main']) && is_array($grp['main']) && !empty($grp['main']['id'])) {
            $allowed[(string)$grp['main']['id']] = true;
        }
        if (!empty($grp['companions']) && is_array($grp['companions'])) {
            foreach ($grp['companions'] as $c) {
                if (!is_array($c) || empty($c['id'])) continue;
                $allowed[(string)$c['id']] = true;
            }
        }
        $allAllowedIds = array_keys($allowed);

        $raw = $data['groups'];
        $has2 = isset($raw[$SG2]) && is_array($raw[$SG2]);
        $has3 = isset($raw[$SG3]) && is_array($raw[$SG3]);

        $seen = [];

        $g2 = [];
        if ($has2) {
            $m = $raw[$SG2]['members'] ?? [];
            if (!is_array($m)) $m = [];
            foreach ($m as $id) {
                $id = trim((string)$id);
                if ($id === '' || !isset($allowed[$id]) || isset($seen[$id])) continue;
                $seen[$id] = true;
                $g2[] = $id;
            }
        }

        $g3 = [];
        if ($has3) {
            $m = $raw[$SG3]['members'] ?? [];
            if (!is_array($m)) $m = [];
            foreach ($m as $id) {
                $id = trim((string)$id);
                if ($id === '' || !isset($allowed[$id]) || isset($seen[$id])) continue;
                $seen[$id] = true;
                $g3[] = $id;
            }
        }

        $g1 = [];
        foreach ($allAllowedIds as $id) {
            if (!isset($seen[$id])) $g1[] = $id;
        }

        $clean = [];
        $clean[$SG1] = ['name' => 'Sitzgruppe 1', 'members' => $g1];
        if ($has2) $clean[$SG2] = ['name' => 'Sitzgruppe 2', 'members' => $g2];
        if ($has3) $clean[$SG3] = ['name' => 'Sitzgruppe 3', 'members' => $g3];

        $existing = SeatingService::load($mainId);
        $personNotes = $existing['person_notes'] ?? [];
        if (!is_array($personNotes)) $personNotes = [];

        SeatingService::saveAll($mainId, $clean, $groupNotes, $personNotes);

        header('Location: /seating.php');
        exit;
    }
}
