/**
 * FlappingBadge — injects flapping status badges into the Zabbix Problems list.
 *
 * Strategy (same as IncidentInvestigation's problem-investigation-icon.js):
 *   1. Wait for the problems table to appear in the DOM.
 *   2. Collect visible trigger IDs from each row.
 *   3. Batch-request flapping status from CControllerFlappingBadge.
 *   4. Inject a colored pill badge into each flapping row.
 *   5. Re-run on table mutations (pagination, auto-refresh).
 */
(function () {
	'use strict';

	const cfg = window.FlappingConfig || {
		badgeEndpoint: 'zabbix.php?action=flapping.badge.data',
		timeWindow:    6,
		minFlaps:      3,
		historyUrl:    'zabbix.php?action=flapping.history',
	};

	const BADGE_CLASS   = 'flapping-injected-badge';
	const ROW_MARKED    = 'data-flapping-checked';

	// -------------------------------------------------------------------------
	// Extract the triggerid from a problem row.
	// Zabbix 7.0 stores it in a data attribute or inside an action link href.
	// -------------------------------------------------------------------------
	function extractTriggerId(row) {
		// Method 1: data-triggerid attribute (set by some Zabbix views)
		if (row.dataset.triggerid) return row.dataset.triggerid;

		// Method 2: look for a link containing "triggerid="
		const links = row.querySelectorAll('a[href*="triggerid="]');
		for (const a of links) {
			const m = a.href.match(/triggerid=(\d+)/);
			if (m) return m[1];
		}

		// Method 3: look for a link containing "objectid=" (event detail pages)
		const objLinks = row.querySelectorAll('a[href*="objectid="]');
		for (const a of objLinks) {
			const m = a.href.match(/objectid=(\d+)/);
			if (m) return m[1];
		}

		return null;
	}

	// -------------------------------------------------------------------------
	// Find the best column cell to inject the badge into.
	// We target the "Problem" name cell (usually the 3rd or 4th td).
	// -------------------------------------------------------------------------
	function findNameCell(row) {
		// Try a cell that already has a problem description anchor
		const anchor = row.querySelector('td a.problem-description, td.problem-name, td[class*="problem"]');
		if (anchor) return anchor.closest('td');

		// Fallback: second visible td (skip checkboxes)
		const cells = row.querySelectorAll('td');
		return cells.length > 1 ? cells[1] : null;
	}

	// -------------------------------------------------------------------------
	// Build the badge HTML element
	// -------------------------------------------------------------------------
	function makeBadge(triggerid, info) {
		const pill = document.createElement('a');
		pill.className   = `${BADGE_CLASS} flap-badge ${info.severity}`;
		pill.href        = `${cfg.historyUrl}&triggerid=${triggerid}&time_window=${cfg.timeWindow}`;
		pill.title       = `Flapping: ${info.flap_count} flips in ${cfg.timeWindow}h (${info.flap_rate}/h)`;
		pill.textContent = `⚡ ${info.flap_count}`;
		pill.style.marginLeft = '6px';
		return pill;
	}

	// -------------------------------------------------------------------------
	// Main scan: collect rows, batch-fetch, inject
	// -------------------------------------------------------------------------
	async function scanProblemsTable(table) {
		const rows = Array.from(table.querySelectorAll('tr[data-id], tbody tr'))
			.filter(r => !r.hasAttribute(ROW_MARKED));

		if (!rows.length) return;

		// Mark all rows to avoid re-processing
		rows.forEach(r => r.setAttribute(ROW_MARKED, '1'));

		// Collect triggerids
		const triggerMap = new Map(); // triggerid → row
		rows.forEach(row => {
			const tid = extractTriggerId(row);
			if (tid) triggerMap.set(tid, row);
		});

		if (!triggerMap.size) return;

		// Batch request
		const body = new URLSearchParams();
		body.append('action', 'flapping.badge.data');
		body.append('time_window', cfg.timeWindow);
		body.append('min_flaps', cfg.minFlaps);
		triggerMap.forEach((_, tid) => body.append('triggerids[]', tid));

		let data;
		try {
			const res  = await fetch(cfg.badgeEndpoint, { method: 'POST', body });
			const json = await res.json();
			data = json.flapping || {};
		} catch {
			return; // silent fail — badge is enhancement only
		}

		// Inject badges
		for (const [tid, info] of Object.entries(data)) {
			const row  = triggerMap.get(tid);
			if (!row) continue;

			// Remove stale badge if any
			row.querySelectorAll(`.${BADGE_CLASS}`).forEach(b => b.remove());

			const cell = findNameCell(row);
			if (cell) cell.appendChild(makeBadge(tid, info));
		}
	}

	// -------------------------------------------------------------------------
	// Boot — watch for the problems table
	// -------------------------------------------------------------------------
	function findAndScan() {
		const tables = document.querySelectorAll(
			'table.list-table, .problems-table table, [data-name="problems"] table, table'
		);
		tables.forEach(t => scanProblemsTable(t));
	}

	// MutationObserver — re-scan when the table is refreshed / paginated
	const observer = new MutationObserver(() => findAndScan());

	function boot() {
		findAndScan();
		observer.observe(document.body, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
