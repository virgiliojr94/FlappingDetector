/**
 * FlappingCharts — minimal canvas-based bar chart for the history view.
 * No external dependencies. Inspired by Cloudflare's swimlane timeline.
 */
const FlappingCharts = (() => {
	'use strict';

	/**
	 * Renders a bar chart of flips-per-hour over the time window.
	 * @param {HTMLCanvasElement} canvas
	 * @param {number[]} buckets  - array of flip counts per hour (oldest → newest)
	 * @param {number}   windowH  - total hours (for x-axis labels)
	 */
	function renderHourly(canvas, buckets, windowH) {
		if (!canvas || !buckets.length) return;

		const dpr    = window.devicePixelRatio || 1;
		const W      = canvas.offsetWidth  || 600;
		const H      = canvas.offsetHeight || 120;
		canvas.width  = W * dpr;
		canvas.height = H * dpr;

		const ctx = canvas.getContext('2d');
		ctx.scale(dpr, dpr);

		const PAD_L = 36, PAD_R = 12, PAD_T = 12, PAD_B = 28;
		const chartW = W - PAD_L - PAD_R;
		const chartH = H - PAD_T - PAD_B;

		const max = Math.max(...buckets, 1);
		const n   = buckets.length;
		const barW = Math.max(2, (chartW / n) - 2);

		// Detect dark mode
		const dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
		const colorBg   = dark ? '#1e1e1e' : '#f7f7f5';
		const colorBar  = dark ? '#4f8ef7' : '#2563eb';
		const colorBarH = dark ? '#f87171' : '#dc2626'; // high bars
		const colorText = dark ? '#9ca3af' : '#6b7280';
		const colorGrid = dark ? '#2d2d2d' : '#e5e7eb';

		// Background
		ctx.fillStyle = colorBg;
		ctx.fillRect(0, 0, W, H);

		// Grid lines (2)
		ctx.strokeStyle = colorGrid;
		ctx.lineWidth   = 0.5;
		for (let g = 1; g <= 2; g++) {
			const y = PAD_T + chartH - (g / 2) * chartH;
			ctx.beginPath();
			ctx.moveTo(PAD_L, y);
			ctx.lineTo(PAD_L + chartW, y);
			ctx.stroke();
		}

		// Bars
		for (let i = 0; i < n; i++) {
			const val   = buckets[i];
			if (val === 0) continue;
			const bH    = (val / max) * chartH;
			const x     = PAD_L + i * (chartW / n);
			const y     = PAD_T + chartH - bH;
			ctx.fillStyle = val >= max * 0.75 ? colorBarH : colorBar;
			ctx.fillRect(x + 1, y, barW, bH);
		}

		// Y-axis label (max)
		ctx.fillStyle  = colorText;
		ctx.font       = '10px sans-serif';
		ctx.textAlign  = 'right';
		ctx.fillText(max, PAD_L - 4, PAD_T + 10);
		ctx.fillText('0', PAD_L - 4, PAD_T + chartH);

		// X-axis: show first and last label
		ctx.textAlign = 'left';
		ctx.fillText(`-${windowH}h`, PAD_L, H - 6);
		ctx.textAlign = 'right';
		ctx.fillText('now', PAD_L + chartW, H - 6);

		// X axis line
		ctx.strokeStyle = colorGrid;
		ctx.lineWidth   = 1;
		ctx.beginPath();
		ctx.moveTo(PAD_L, PAD_T + chartH);
		ctx.lineTo(PAD_L + chartW, PAD_T + chartH);
		ctx.stroke();
	}

	return { renderHourly };
})();
