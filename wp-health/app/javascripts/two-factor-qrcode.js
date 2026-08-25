/**
 * QR code for the two-factor setup screen.
 *
 * Byte mode, error correction level L, versions 1 to 10, which tops out at 271
 * bytes and covers any otpauth:// URI this plugin builds. The plugin has no
 * JavaScript build step, so this file ships exactly as it reads here.
 *
 * The code is a convenience, never a requirement: the setup key is printed
 * next to it, and the site only persists a secret once it has verified a code
 * typed back from the app. A QR that fails to draw costs a manual entry.
 */
(function (window, document) {
	'use strict';

	/**
	 * One entry per version, level L only: error correction codewords per block,
	 * then the (block count, data codewords per block) groups the payload is cut
	 * into before interleaving.
	 */
	var VERSIONS = [
		{ ec: 7, groups: [[1, 19]] },
		{ ec: 10, groups: [[1, 34]] },
		{ ec: 15, groups: [[1, 55]] },
		{ ec: 20, groups: [[1, 80]] },
		{ ec: 26, groups: [[1, 108]] },
		{ ec: 18, groups: [[2, 68]] },
		{ ec: 20, groups: [[2, 78]] },
		{ ec: 24, groups: [[2, 97]] },
		{ ec: 30, groups: [[2, 116]] },
		{ ec: 18, groups: [[2, 68], [2, 69]] }
	];

	var ALIGNMENT = [
		[],
		[6, 18],
		[6, 22],
		[6, 26],
		[6, 30],
		[6, 34],
		[6, 22, 38],
		[6, 24, 42],
		[6, 26, 46],
		[6, 28, 50]
	];

	var FORMAT_BITS_LEVEL_L = 1;

	var EXP = [];
	var LOG = [];

	(function buildGaloisTables() {
		var value = 1;

		for (var i = 0; i < 255; i++) {
			EXP[i] = value;
			LOG[value] = i;
			value <<= 1;

			if (value & 0x100) {
				value ^= 0x11d;
			}
		}

		for (var j = 255; j < 512; j++) {
			EXP[j] = EXP[j - 255];
		}
	})();

	function gfMultiply(a, b) {
		if (a === 0 || b === 0) {
			return 0;
		}

		return EXP[LOG[a] + LOG[b]];
	}

	function generatorPolynomial(degree) {
		var poly = [1];

		for (var i = 0; i < degree; i++) {
			var next = [];

			for (var n = 0; n <= poly.length; n++) {
				next[n] = 0;
			}

			for (var j = 0; j < poly.length; j++) {
				next[j] ^= poly[j];
				next[j + 1] ^= gfMultiply(poly[j], EXP[i]);
			}

			poly = next;
		}

		return poly;
	}

	function reedSolomon(data, ecLength) {
		var generator = generatorPolynomial(ecLength);
		var remainder = [];

		for (var i = 0; i < ecLength; i++) {
			remainder[i] = 0;
		}

		for (var d = 0; d < data.length; d++) {
			var factor = data[d] ^ remainder[0];

			remainder.shift();
			remainder.push(0);

			if (factor === 0) {
				continue;
			}

			for (var g = 0; g < ecLength; g++) {
				remainder[g] ^= gfMultiply(generator[g + 1], factor);
			}
		}

		return remainder;
	}

	function toUtf8Bytes(text) {
		var bytes = [];

		for (var i = 0; i < text.length; i++) {
			var code = text.charCodeAt(i);

			if (code < 0x80) {
				bytes.push(code);
			} else if (code < 0x800) {
				bytes.push(0xc0 | (code >> 6), 0x80 | (code & 0x3f));
			} else {
				bytes.push(0xe0 | (code >> 12), 0x80 | ((code >> 6) & 0x3f), 0x80 | (code & 0x3f));
			}
		}

		return bytes;
	}

	function dataCapacity(version) {
		var total = 0;

		VERSIONS[version - 1].groups.forEach(function (group) {
			total += group[0] * group[1];
		});

		return total;
	}

	function pickVersion(byteLength) {
		for (var version = 1; version <= VERSIONS.length; version++) {
			var headerBits = 4 + (version >= 10 ? 16 : 8);
			var available = dataCapacity(version) * 8 - headerBits;

			if (byteLength * 8 <= available) {
				return version;
			}
		}

		throw new Error('payload too long for a version 10 QR code');
	}

	function buildCodewords(bytes, version) {
		var capacity = dataCapacity(version);
		var bits = [];

		function push(value, length) {
			for (var i = length - 1; i >= 0; i--) {
				bits.push((value >> i) & 1);
			}
		}

		push(4, 4);
		push(bytes.length, version >= 10 ? 16 : 8);

		for (var i = 0; i < bytes.length; i++) {
			push(bytes[i], 8);
		}

		push(0, Math.min(4, capacity * 8 - bits.length));

		while (bits.length % 8 !== 0) {
			bits.push(0);
		}

		var padding = [0xec, 0x11];
		var pad = 0;

		while (bits.length < capacity * 8) {
			push(padding[pad++ % 2], 8);
		}

		var codewords = [];

		for (var b = 0; b < bits.length; b += 8) {
			var codeword = 0;

			for (var k = 0; k < 8; k++) {
				codeword = (codeword << 1) | bits[b + k];
			}

			codewords.push(codeword);
		}

		return codewords;
	}

	function interleave(codewords, version) {
		var spec = VERSIONS[version - 1];
		var dataBlocks = [];
		var ecBlocks = [];
		var offset = 0;
		var longest = 0;

		spec.groups.forEach(function (group) {
			for (var b = 0; b < group[0]; b++) {
				var block = codewords.slice(offset, offset + group[1]);

				offset += group[1];
				longest = Math.max(longest, block.length);
				dataBlocks.push(block);
				ecBlocks.push(reedSolomon(block, spec.ec));
			}
		});

		var result = [];
		var i;
		var j;

		for (i = 0; i < longest; i++) {
			for (j = 0; j < dataBlocks.length; j++) {
				if (i < dataBlocks[j].length) {
					result.push(dataBlocks[j][i]);
				}
			}
		}

		for (i = 0; i < spec.ec; i++) {
			for (j = 0; j < ecBlocks.length; j++) {
				result.push(ecBlocks[j][i]);
			}
		}

		return result;
	}

	function createMatrix(version) {
		var size = version * 4 + 17;
		var modules = [];
		var reserved = [];
		var x;
		var y;
		var i;

		for (y = 0; y < size; y++) {
			modules[y] = [];
			reserved[y] = [];

			for (x = 0; x < size; x++) {
				modules[y][x] = false;
				reserved[y][x] = false;
			}
		}

		function setFunctionModule(px, py, isDark) {
			if (px < 0 || py < 0 || px >= size || py >= size) {
				return;
			}

			modules[py][px] = isDark;
			reserved[py][px] = true;
		}

		function drawFinder(cx, cy) {
			for (var dy = -4; dy <= 4; dy++) {
				for (var dx = -4; dx <= 4; dx++) {
					var distance = Math.max(Math.abs(dx), Math.abs(dy));

					setFunctionModule(cx + dx, cy + dy, distance !== 2 && distance !== 4);
				}
			}
		}

		function drawAlignment(cx, cy) {
			for (var dy = -2; dy <= 2; dy++) {
				for (var dx = -2; dx <= 2; dx++) {
					setFunctionModule(cx + dx, cy + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
				}
			}
		}

		drawFinder(3, 3);
		drawFinder(size - 4, 3);
		drawFinder(3, size - 4);

		for (i = 0; i < size; i++) {
			if (!reserved[6][i]) {
				setFunctionModule(i, 6, i % 2 === 0);
			}

			if (!reserved[i][6]) {
				setFunctionModule(6, i, i % 2 === 0);
			}
		}

		var centers = ALIGNMENT[version - 1];

		for (i = 0; i < centers.length; i++) {
			for (var k = 0; k < centers.length; k++) {
				var cx = centers[i];
				var cy = centers[k];
				var onFinder = (cx === 6 && cy === 6) ||
					(cx === 6 && cy === size - 7) ||
					(cx === size - 7 && cy === 6);

				if (!onFinder) {
					drawAlignment(cx, cy);
				}
			}
		}

		for (i = 0; i <= 8; i++) {
			if (i !== 6) {
				setFunctionModule(8, i, false);
				setFunctionModule(i, 8, false);
			}
		}

		for (i = 0; i < 8; i++) {
			setFunctionModule(size - 1 - i, 8, false);
			setFunctionModule(8, size - 1 - i, false);
		}

		if (version >= 7) {
			var remainder = version;

			for (i = 0; i < 12; i++) {
				remainder = (remainder << 1) ^ ((remainder >>> 11) * 0x1f25);
			}

			var versionBits = (version << 12) | remainder;

			for (i = 0; i < 18; i++) {
				var bit = ((versionBits >>> i) & 1) === 1;
				var a = Math.floor(i / 3);
				var b = i % 3;

				setFunctionModule(a, size - 11 + b, bit);
				setFunctionModule(size - 11 + b, a, bit);
			}
		}

		return { size: size, modules: modules, reserved: reserved, setFunctionModule: setFunctionModule };
	}

	function placeData(matrix, codewords) {
		var size = matrix.size;
		var index = 0;
		var total = codewords.length * 8;

		for (var right = size - 1; right >= 1; right -= 2) {
			if (right === 6) {
				right = 5;
			}

			for (var vertical = 0; vertical < size; vertical++) {
				for (var j = 0; j < 2; j++) {
					var x = right - j;
					var upward = ((right + 1) & 2) === 0;
					var y = upward ? size - 1 - vertical : vertical;

					if (matrix.reserved[y][x] || index >= total) {
						continue;
					}

					matrix.modules[y][x] = ((codewords[index >>> 3] >>> (7 - (index & 7))) & 1) !== 0;
					index++;
				}
			}
		}
	}

	function maskCondition(mask, x, y) {
		switch (mask) {
			case 0:
				return (x + y) % 2 === 0;
			case 1:
				return y % 2 === 0;
			case 2:
				return x % 3 === 0;
			case 3:
				return (x + y) % 3 === 0;
			case 4:
				return (Math.floor(y / 2) + Math.floor(x / 3)) % 2 === 0;
			case 5:
				return ((x * y) % 2) + ((x * y) % 3) === 0;
			case 6:
				return (((x * y) % 2) + ((x * y) % 3)) % 2 === 0;
			default:
				return (((x + y) % 2) + ((x * y) % 3)) % 2 === 0;
		}
	}

	function applyMask(matrix, mask) {
		for (var y = 0; y < matrix.size; y++) {
			for (var x = 0; x < matrix.size; x++) {
				if (!matrix.reserved[y][x] && maskCondition(mask, x, y)) {
					matrix.modules[y][x] = !matrix.modules[y][x];
				}
			}
		}
	}

	function drawFormatBits(matrix, mask) {
		var data = (FORMAT_BITS_LEVEL_L << 3) | mask;
		var remainder = data;
		var i;

		for (i = 0; i < 10; i++) {
			remainder = (remainder << 1) ^ ((remainder >>> 9) * 0x537);
		}

		var bits = ((data << 10) | remainder) ^ 0x5412;
		var size = matrix.size;

		function bitAt(position) {
			return ((bits >>> position) & 1) === 1;
		}

		for (i = 0; i <= 5; i++) {
			matrix.setFunctionModule(8, i, bitAt(i));
		}

		matrix.setFunctionModule(8, 7, bitAt(6));
		matrix.setFunctionModule(8, 8, bitAt(7));
		matrix.setFunctionModule(7, 8, bitAt(8));

		for (i = 9; i < 15; i++) {
			matrix.setFunctionModule(14 - i, 8, bitAt(i));
		}

		for (i = 0; i < 8; i++) {
			matrix.setFunctionModule(size - 1 - i, 8, bitAt(i));
		}

		for (i = 8; i < 15; i++) {
			matrix.setFunctionModule(8, size - 15 + i, bitAt(i));
		}

		matrix.setFunctionModule(8, size - 8, true);
	}

	function runPenalty(line) {
		var penalty = 0;
		var run = 1;

		for (var i = 1; i < line.length; i++) {
			if (line[i] === line[i - 1]) {
				run++;
				continue;
			}

			if (run >= 5) {
				penalty += 3 + (run - 5);
			}

			run = 1;
		}

		if (run >= 5) {
			penalty += 3 + (run - 5);
		}

		return penalty;
	}

	var FINDER_FORWARD = [true, false, true, true, true, false, true, false, false, false, false];

	var FINDER_BACKWARD = [false, false, false, false, true, false, true, true, true, false, true];

	function matchesAt(line, start, pattern) {
		for (var i = 0; i < pattern.length; i++) {
			if (line[start + i] !== pattern[i]) {
				return false;
			}
		}

		return true;
	}

	function finderPenalty(line) {
		var penalty = 0;

		for (var i = 0; i + 10 < line.length; i++) {
			if (matchesAt(line, i, FINDER_FORWARD)) {
				penalty += 40;
			}

			if (matchesAt(line, i, FINDER_BACKWARD)) {
				penalty += 40;
			}
		}

		return penalty;
	}

	function penaltyScore(matrix) {
		var size = matrix.size;
		var modules = matrix.modules;
		var score = 0;
		var dark = 0;
		var x;
		var y;

		for (y = 0; y < size; y++) {
			var row = modules[y];
			var column = [];

			for (x = 0; x < size; x++) {
				column.push(modules[x][y]);

				if (modules[y][x]) {
					dark++;
				}
			}

			score += runPenalty(row) + runPenalty(column);
			score += finderPenalty(row) + finderPenalty(column);
		}

		for (y = 0; y < size - 1; y++) {
			for (x = 0; x < size - 1; x++) {
				var value = modules[y][x];

				if (value === modules[y][x + 1] && value === modules[y + 1][x] && value === modules[y + 1][x + 1]) {
					score += 3;
				}
			}
		}

		var ratio = (dark * 100) / (size * size);

		score += Math.floor(Math.abs(ratio - 50) / 5) * 10;

		return score;
	}

	/**
	 * The mask is a legibility heuristic, not part of decoding: the format bits
	 * say which one was applied, so every mask reads back the same. forcedMask
	 * pins it so the encoder can be compared module by module against a
	 * reference implementation.
	 *
	 * @param {string} text
	 * @param {number} [forcedMask]
	 *
	 * @return {{size: number, modules: boolean[][], mask: number}}
	 */
	function encode(text, forcedMask) {
		var bytes = toUtf8Bytes(text);
		var version = pickVersion(bytes.length);
		var codewords = interleave(buildCodewords(bytes, version), version);
		var best = null;

		for (var mask = 0; mask < 8; mask++) {
			if (forcedMask !== undefined && mask !== forcedMask) {
				continue;
			}

			var matrix = createMatrix(version);

			placeData(matrix, codewords);
			applyMask(matrix, mask);
			drawFormatBits(matrix, mask);

			var score = penaltyScore(matrix);

			if (best === null || score < best.score) {
				best = { score: score, matrix: matrix, mask: mask };
			}
		}

		return { size: best.matrix.size, modules: best.matrix.modules, mask: best.mask };
	}

	/**
	 * @param {string} text
	 * @param {string} label
	 *
	 * @return {string}
	 */
	function toSvg(text, label) {
		var code = encode(text);
		var quiet = 4;
		var span = code.size + quiet * 2;
		var path = [];

		for (var y = 0; y < code.size; y++) {
			for (var x = 0; x < code.size; x++) {
				if (code.modules[y][x]) {
					path.push('M' + (x + quiet) + ' ' + (y + quiet) + 'h1v1h-1z');
				}
			}
		}

		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + span + ' ' + span + '"' +
			' width="200" height="200" role="img" aria-label="' + label.replace(/"/g, '&quot;') + '">' +
			'<rect width="' + span + '" height="' + span + '" fill="#ffffff"/>' +
			'<path d="' + path.join('') + '" fill="#000000"/>' +
			'</svg>';
	}

	function render() {
		var host = document.getElementById('wpu-2fa-qr');

		if (!host) {
			return;
		}

		var target = document.getElementById('wpu-2fa-qr-canvas');
		var uri = host.getAttribute('data-wpu-qr-uri');

		if (!target || !uri) {
			return;
		}

		var svg;

		try {
			svg = toSvg(uri, host.getAttribute('data-wpu-qr-label') || '');
		} catch (error) {
			return;
		}

		target.innerHTML = svg;
		host.removeAttribute('hidden');
	}

	window.wpUmbrellaQrCode = { encode: encode, toSvg: toSvg };

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', render);
	} else {
		render();
	}
})(window, document);
