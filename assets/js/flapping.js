const FlappingCharts = (() => {
	'use strict';

	function getCssVar(element, name, fallback) {
		const value = window.getComputedStyle(element).getPropertyValue(name).trim();
		return value || fallback;
	}

	function renderHourly(canvas, buckets, windowH) {
		if (!canvas || !Array.isArray(buckets) || buckets.length === 0) {
			return;
		}

		const dpr = window.devicePixelRatio || 1;
		const width = canvas.offsetWidth || 600;
		const height = canvas.offsetHeight || 220;
		const ctx = canvas.getContext('2d');

		if (!ctx) {
			return;
		}

		canvas.width = width * dpr;
		canvas.height = height * dpr;
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

		const PAD_L = 36;
		const PAD_R = 14;
		const PAD_T = 16;
		const PAD_B = 30;
		const chartW = width - PAD_L - PAD_R;
		const chartH = height - PAD_T - PAD_B;
		const max = Math.max(...buckets, 1);
		const count = buckets.length;
		const step = chartW / count;
		const barW = Math.max(2, step - 3);
		const scope = canvas.closest('.flapping-page') || canvas;
		const colorBg = getCssVar(scope, '--flap-chart-bg', '#f0f5fa');
		const colorBar = getCssVar(scope, '--flap-chart-bar', '#2d79d1');
		const colorHotBar = getCssVar(scope, '--flap-chart-hot', '#d74a4a');
		const colorText = getCssVar(scope, '--flap-chart-text', '#627086');
		const colorGrid = getCssVar(scope, '--flap-chart-grid', 'rgba(44, 60, 80, 0.12)');

		ctx.fillStyle = colorBg;
		ctx.fillRect(0, 0, width, height);

		ctx.strokeStyle = colorGrid;
		ctx.lineWidth = 0.5;
		for (let line = 1; line <= 2; line++) {
			const y = PAD_T + chartH - (line / 2) * chartH;
			ctx.beginPath();
			ctx.moveTo(PAD_L, y);
			ctx.lineTo(PAD_L + chartW, y);
			ctx.stroke();
		}

		for (let i = 0; i < count; i++) {
			const value = buckets[i];
			if (value === 0) {
				continue;
			}

			const barH = (value / max) * chartH;
			const x = PAD_L + i * step;
			const y = PAD_T + chartH - barH;

			ctx.fillStyle = value >= max * 0.75 ? colorHotBar : colorBar;
			ctx.fillRect(x + 1.5, y, barW, barH);
		}

		ctx.fillStyle = colorText;
		ctx.font = '10px sans-serif';
		ctx.textAlign = 'right';
		ctx.fillText(String(max), PAD_L - 4, PAD_T + 10);
		ctx.fillText('0', PAD_L - 4, PAD_T + chartH);

		ctx.textAlign = 'left';
		ctx.fillText(`-${windowH}h`, PAD_L, height - 8);
		ctx.textAlign = 'right';
		ctx.fillText('now', PAD_L + chartW, height - 8);

		ctx.strokeStyle = colorGrid;
		ctx.lineWidth = 1;
		ctx.beginPath();
		ctx.moveTo(PAD_L, PAD_T + chartH);
		ctx.lineTo(PAD_L + chartW, PAD_T + chartH);
		ctx.stroke();
	}

	return { renderHourly };
})();
