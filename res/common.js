function $(sel) { return document.querySelector(sel); }
function $$(sel) { return document.querySelectorAll(sel); }

function modulo(divident, divisor) {
	// Via http://stackoverflow.com/a/2772402/1201863
	// cc by-sa 3.0 with attribution required
	divident = divident.toString();
	divisor = divisor.toString();
	var cDivident = '';
	var cRest = '';

	for (var i in divident) {
		var cChar = divident[i];
		var cOperator = cRest + '' + cDivident + '' + cChar;

		if (cOperator < parseInt(divisor)) {
			cDivident += '' + cChar;
		}
		else {
			cRest = cOperator % divisor;
			if (cRest == 0) {
				cRest = '';
			}
			cDivident = '';
		}

	}
	cRest += '' + cDivident;
	if (cRest == '') {
		cRest = 0;
	}
	return cRest;
}

function lucHash(str) {
	var seed = 131;
	var hash = seed;
	for (var i in str) {
		// Original implementations used uint, so let's make sure our output falls in the same range and do a modulo.
		// Unfortunately, Javascript is not python and silently fails at modulo on large numbers.
		// The custom modulo() function is also not perfect, still failing at very large numbers, but it *probably* works for this use case. More testing is needed to be certain.
		hash = parseInt(modulo(hash + (Math.max(0.3, 10 / (i + 1)) * str.charCodeAt(i)), Math.pow(2, 32)));
	}
	return hash;
}

function CSSHSLHash(str, saturation, lightness) {
	return 'hsl(' + modulo(lucHash(str.toLowerCase()), 360) + ', ' + saturation + '%, ' + lightness + '%)';
}
