const FlappingCharts = (() => {
	'use strict';

	function isDarkTheme() {
		const bodyTheme = document.body ? document.body.getAttribute('theme') : null;
		const documentTheme = document.documentElement.getAttribute('theme');

		return bodyTheme === 'dark-theme'
			|| documentTheme === 'dark-theme'
			|| window.matchMedia('(prefers-color-scheme: dark)').matches;
	}

	function renderHourly(canvas, buckets, windowH) {
		if (!canvas || !Array.isArray(buckets) || buckets.length === 0) {
			return;
		}

		const dpr = window.devicePixelRatio || 1;
		const width = canvas.offsetWidth || 600;
		const height = canvas.offsetHeight || 120;
		const ctx = canvas.getContext('2d');

		if (!ctx) {
			return;
		}

		canvas.width = width * dpr;
		canvas.height = height * dpr;
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

		const PAD_L = 36;
		const PAD_R = 12;
		const PAD_T = 12;
		const PAD_B = 28;
		const chartW = width - PAD_L - PAD_R;
		const chartH = height - PAD_T - PAD_B;
		const max = Math.max(...buckets, 1);
		const count = buckets.length;
		const step = chartW / count;
		const barW = Math.max(2, step - 2);
		const dark = isDarkTheme();
		const colorBg = dark ? '#1f1f23' : '#f7f7f5';
		const colorBar = dark ? '#4f8ef7' : '#2563eb';
		const colorHotBar = dark ? '#f87171' : '#dc2626';
		const colorText = dark ? '#9ca3af' : '#6b7280';
		const colorGrid = dark ? '#2d2d2d' : '#e5e7eb';

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
			ctx.fillRect(x + 1, y, barW, barH);
		}

		ctx.fillStyle = colorText;
		ctx.font = '10px sans-serif';
		ctx.textAlign = 'right';
		ctx.fillText(String(max), PAD_L - 4, PAD_T + 10);
		ctx.fillText('0', PAD_L - 4, PAD_T + chartH);

		ctx.textAlign = 'left';
		ctx.fillText(`-${windowH}h`, PAD_L, height - 6);
		ctx.textAlign = 'right';
		ctx.fillText('now', PAD_L + chartW, height - 6);

		ctx.strokeStyle = colorGrid;
		ctx.lineWidth = 1;
		ctx.beginPath();
		ctx.moveTo(PAD_L, PAD_T + chartH);
		ctx.lineTo(PAD_L + chartW, PAD_T + chartH);
		ctx.stroke();
	}

	return { renderHourly };
})();
