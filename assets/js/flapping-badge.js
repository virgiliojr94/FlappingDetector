(function() {
	'use strict';

	const cfg = Object.assign({
		badgeEndpoint: 'zabbix.php?action=flapping.badge.data',
		timeWindow: 6,
		minFlaps: 3,
		historyUrl: 'zabbix.php?action=flapping.history'
	}, window.FlappingConfig || {});

	const BADGE_CLASS = 'flapping-injected-badge';
	let scanTimer = null;
	let retryTimer = null;
	let requestToken = 0;

	function isProblemsContext() {
		return window.location.href.includes('action=problem.view')
			|| window.location.href.includes('action=widget.problems.view')
			|| document.getElementById('monitoring_problem_filter') !== null
			|| document.querySelector('form[name="problem"]') !== null
			|| document.querySelector('[data-page="problem"]') !== null
			|| document.querySelectorAll('.dashboard-grid-widget-contents.dashboard-widget-problems').length > 0;
	}

	function getProblemsTables() {
		const tables = [];
		const seen = new Set();

		function add(table) {
			if (table && table.querySelector && !seen.has(table)) {
				seen.add(table);
				tables.push(table);
			}
		}

		const flickerfree = document.querySelector('.flickerfreescreen');
		if (flickerfree) {
			flickerfree.querySelectorAll('table.list-table').forEach(add);
		}

		document.querySelectorAll('.dashboard-grid-widget-contents.dashboard-widget-problems').forEach(widget => {
			const table = widget.querySelector('table.list-table')
				|| (widget.tagName === 'TABLE' && widget.classList.contains('list-table') ? widget : null);

			if (table) {
				add(table);
			}
		});

		const standaloneTable = document.querySelector('table.overflow-ellipsis')
			|| document.querySelector('form[name="problem"] table');
		if (standaloneTable) {
			add(standaloneTable);
		}

		document.querySelectorAll('table.list-table').forEach(add);

		return tables;
	}

	function parseMenuPopup(raw) {
		if (!raw) {
			return null;
		}

		try {
			return JSON.parse(raw);
		}
		catch {
			return null;
		}
	}

	function findTriggerData(row) {
		const menuLinks = row.querySelectorAll('a.link-action[data-menu-popup], [data-menu-popup]');

		for (const link of menuLinks) {
			const menu = parseMenuPopup(link.getAttribute('data-menu-popup'));
			if (menu && menu.type === 'trigger' && menu.data && menu.data.triggerid) {
				return {
					triggerid: String(menu.data.triggerid),
					anchor: link.closest('a') || link
				};
			}
		}

		if (row.dataset.triggerid) {
			return {
				triggerid: String(row.dataset.triggerid),
				anchor: row.querySelector('a[href*="triggerid="], a[href*="objectid="]')
			};
		}

		const triggerLink = row.querySelector('a[href*="triggerid="]');
		if (triggerLink && triggerLink.href) {
			const match = triggerLink.href.match(/triggerid=(\d+)/);
			if (match) {
				return {
					triggerid: match[1],
					anchor: triggerLink
				};
			}
		}

		const objectLink = row.querySelector('a[href*="objectid="]');
		if (objectLink && objectLink.href) {
			const match = objectLink.href.match(/objectid=(\d+)/);
			if (match) {
				return {
					triggerid: match[1],
					anchor: objectLink
				};
			}
		}

		return null;
	}

	function getInsertCell(row, anchor) {
		if (anchor) {
			const anchorCell = anchor.closest('td');
			if (anchorCell) {
				return anchorCell;
			}
		}

		const problemCell = row.querySelector('td.problem-name, td[class*="problem"], td a.problem-description');
		if (problemCell) {
			return problemCell.closest('td') || problemCell;
		}

		const actionsCell = row.querySelector('td.list-table-actions, td .list-table-actions');
		if (actionsCell) {
			return actionsCell.closest('td') || actionsCell;
		}

		return row.querySelector('td:last-child');
	}

	function makeBadge(triggerid, info) {
		const pill = document.createElement('a');
		pill.className = `${BADGE_CLASS} flap-badge ${info.severity}`;
		pill.href = `${cfg.historyUrl}&triggerid=${encodeURIComponent(triggerid)}&time_window=${encodeURIComponent(cfg.timeWindow)}`;
		pill.title = `Flapping: ${info.flap_count} flips in ${cfg.timeWindow}h (${info.flap_rate}/h)`;
		pill.textContent = `⚡ ${info.flap_count}`;
		pill.setAttribute('aria-label', pill.title);
		pill.style.marginLeft = '6px';

		return pill;
	}

	function collectRows() {
		const collected = [];

		getProblemsTables().forEach(table => {
			const rows = table.querySelectorAll('tbody tr:not(.timeline-axis):not(.timeline-td)');

			rows.forEach(row => {
				const triggerData = findTriggerData(row);
				if (!triggerData) {
					return;
				}

				const cell = getInsertCell(row, triggerData.anchor);
				if (!cell) {
					return;
				}

				collected.push({
					row,
					cell,
					triggerid: triggerData.triggerid
				});
			});
		});

		return collected;
	}

	async function injectBadges() {
		if (!isProblemsContext()) {
			return;
		}

		const rows = collectRows();
		if (!rows.length) {
			return;
		}

		const triggerids = [...new Set(rows.map(entry => entry.triggerid))];
		const token = ++requestToken;
		const body = new URLSearchParams();

		body.append('time_window', String(cfg.timeWindow));
		body.append('min_flaps', String(cfg.minFlaps));
		triggerids.forEach(triggerid => body.append('triggerids[]', triggerid));

		const response = await fetch(cfg.badgeEndpoint, {
			method: 'POST',
			body,
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		});

		if (!response.ok) {
			throw new Error(`Badge request failed with status ${response.status}`);
		}

		const payload = await response.json();
		if (token !== requestToken) {
			return;
		}

		const flapping = payload.flapping || {};

		rows.forEach(({ row, cell, triggerid }) => {
			row.querySelectorAll(`.${BADGE_CLASS}`).forEach(badge => badge.remove());

			if (flapping[triggerid]) {
				cell.appendChild(makeBadge(triggerid, flapping[triggerid]));
			}
		});
	}

	function scheduleScan(delay = 75) {
		if (!isProblemsContext()) {
			return;
		}

		if (scanTimer !== null) {
			clearTimeout(scanTimer);
		}

		scanTimer = setTimeout(() => {
			injectBadges().catch(() => {
				if (retryTimer !== null) {
					clearTimeout(retryTimer);
				}

				retryTimer = setTimeout(() => scheduleScan(0), 5000);
			});
		}, delay);
	}

	function init() {
		if (!isProblemsContext()) {
			return;
		}

		scheduleScan(0);

		const observer = new MutationObserver(mutations => {
			for (const mutation of mutations) {
				if (mutation.addedNodes.length > 0) {
					scheduleScan();
					return;
				}
			}
		});

		const target = document.querySelector('.wrapper') || document.body;
		if (target) {
			observer.observe(target, {
				childList: true,
				subtree: true
			});
		}

		if (typeof $ !== 'undefined') {
			$(document).on('complete.view', () => scheduleScan(50));
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	}
	else {
		init();
	}
})();
