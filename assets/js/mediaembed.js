function lazyload(anchor) {
	setTimeout(function () {
		// Strip comment tags around innerHTML
		anchor.innerHTML = anchor.innerHTML.replace('<!--', '').replace('-->', '');
	}, 1000);

	// Remove <noscript> tag in element DOM
	var elem = anchor.getElementsByTagName('noscript');
	if ( elem.length > 0 ) {
		elem[0].remove();
	}

	// Suppress further onClick events
	anchor.removeAttribute('href');
	anchor.onclick = null;

	return false;
}
