/**
 * Hale Components — Block Usage report.
 *
 * Scans the network one site per request so a large network can't blow the
 * PHP time limit, appending each site's results as they arrive.
 */
(function () {
	'use strict';

	var settings = window.hcBlockUsage || {};
	var i18n = settings.i18n || {};

	var form = document.getElementById( 'hc-block-usage-form' );
	var input = document.getElementById( 'hc-block-usage-name' );
	var submit = document.getElementById( 'hc-block-usage-submit' );
	var stopBtn = document.getElementById( 'hc-block-usage-stop' );
	var progress = document.getElementById( 'hc-block-usage-progress' );
	var progressText = progress ? progress.querySelector( '.hc-block-usage-progress-text' ) : null;
	var progressBar = progress ? progress.querySelector( '.hc-block-usage-bar span' ) : null;
	var summary = document.getElementById( 'hc-block-usage-summary' );
	var results = document.getElementById( 'hc-block-usage-results' );
	var empty = document.getElementById( 'hc-block-usage-empty' );
	var exportForm = document.getElementById( 'hc-block-usage-export-form' );
	var exportBlock = document.getElementById( 'hc-block-usage-export-block' );
	var exportData = document.getElementById( 'hc-block-usage-export-data' );

	if ( ! form || ! settings.ajaxUrl ) {
		return;
	}

	var state = {
		running: false,
		aborted: false,
		block: '',
		index: 0,
		sites: [],
		instances: 0,
		posts: 0,
		sitesUsing: 0,
		rows: [],
		startedAt: 0
	};

	/* --------------------------------------------------------------- utils */

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );

		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( key ) {
				if ( null !== attrs[ key ] && undefined !== attrs[ key ] ) {
					node.setAttribute( key, attrs[ key ] );
				}
			} );
		}

		if ( undefined !== text && null !== text ) {
			node.textContent = String( text );
		}

		return node;
	}

	function sprintf( template, values ) {
		var out = String( template || '' );

		values.forEach( function ( value, index ) {
			out = out.replace( new RegExp( '%' + ( index + 1 ) + '\\$s', 'g' ), value );
		} );

		return out.replace( '%s', values[ 0 ] );
	}

	function number( value ) {
		return String( value ).replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
	}

	/* ---------------------------------------------------------------- scan */

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();

		if ( state.running ) {
			return;
		}

		var block = ( input.value || '' ).trim();

		if ( ! block ) {
			window.alert( i18n.invalid ); // eslint-disable-line no-alert
			input.focus();
			return;
		}

		start( block );
	} );

	if ( stopBtn ) {
		stopBtn.addEventListener( 'click', function () {
			state.aborted = true;
		} );
	}

	function start( block ) {
		state.running = true;
		state.aborted = false;
		state.block = block;
		state.index = 0;
		state.sites = ( settings.sites || [] ).slice();
		state.instances = 0;
		state.posts = 0;
		state.sitesUsing = 0;
		state.rows = [];
		state.startedAt = Date.now();

		results.innerHTML = '';
		summary.innerHTML = '';
		summary.hidden = true;
		empty.hidden = true;
		progress.hidden = false;
		submit.disabled = true;
		stopBtn.hidden = false;

		renderProgress();
		next();
	}

	function next() {
		if ( state.aborted || state.index >= state.sites.length ) {
			finish();
			return;
		}

		var site = state.sites[ state.index ];

		scanSite( site, function () {
			state.index++;
			renderProgress();
			next();
		} );
	}

	function scanSite( site, done ) {
		var body = new URLSearchParams();

		body.append( 'action', 'hc_block_usage_scan' );
		body.append( 'nonce', settings.nonce );
		body.append( 'block', state.block );
		body.append( 'site_id', site.id );

		window.fetch( settings.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( ! payload || ! payload.success ) {
					renderError( site, payload && payload.data ? payload.data.message : '' );
					done();
					return;
				}

				addSiteResult( payload.data );
				done();
			} )
			.catch( function () {
				renderError( site, '' );
				done();
			} );
	}

	function addSiteResult( data ) {
		if ( ! data || ! data.instances ) {
			return;
		}

		state.instances += data.instances;
		state.posts += data.postCount;
		state.sitesUsing++;

		data.posts.forEach( function ( post ) {
			state.rows.push( {
				siteId: data.site.id,
				siteName: data.site.name,
				siteUrl: data.site.url,
				id: post.id,
				title: post.title,
				type: post.type,
				status: post.status,
				count: post.count,
				view: post.view,
				edit: post.edit
			} );
		} );

		results.appendChild( renderSite( data ) );
		renderSummary();
	}

	/* -------------------------------------------------------------- render */

	function renderProgress() {
		var total = state.sites.length;
		var done = state.index;
		var percent = total ? Math.round( ( done / total ) * 100 ) : 100;

		progressBar.style.width = percent + '%';
		progressText.textContent = sprintf( i18n.scanning, [ number( Math.min( done + 1, total ) ), number( total ) ] );
	}

	function renderSummary() {
		summary.hidden = false;
		summary.innerHTML = '';

		[
			[ i18n.instances, state.instances ],
			[ i18n.posts, state.posts ],
			[ i18n.sitesUsing, state.sitesUsing ]
		].forEach( function ( pair ) {
			var tile = el( 'div', { class: 'hc-block-usage-tile' } );
			tile.appendChild( el( 'span', { class: 'hc-block-usage-tile-number' }, number( pair[ 1 ] ) ) );
			tile.appendChild( el( 'span', { class: 'hc-block-usage-tile-label' }, pair[ 0 ] ) );
			summary.appendChild( tile );
		} );
	}

	function renderSite( data ) {
		var section = el( 'div', { class: 'hc-block-usage-site' } );
		var heading = el( 'h2' );
		var link = el( 'a', { href: data.site.url, target: '_blank', rel: 'noopener' }, data.site.name || data.site.url );

		heading.appendChild( link );
		heading.appendChild( el( 'span', { class: 'hc-block-usage-site-id' }, sprintf( i18n.siteId, [ data.site.id ] ) ) );
		section.appendChild( heading );

		section.appendChild(
			el( 'p', { class: 'hc-block-usage-subtotal' }, sprintf( i18n.subtotal, [ number( data.instances ), number( data.postCount ) ] ) )
		);

		var table = el( 'table', { class: 'widefat striped hc-block-usage-table' } );
		var thead = el( 'thead' );
		var headRow = el( 'tr' );

		[ i18n.post, i18n.type, i18n.status, i18n.used, i18n.actions ].forEach( function ( label ) {
			headRow.appendChild( el( 'th', { scope: 'col' }, label ) );
		} );

		thead.appendChild( headRow );
		table.appendChild( thead );

		var tbody = el( 'tbody' );

		data.posts.forEach( function ( post ) {
			var row = el( 'tr' );
			var titleCell = el( 'td' );

			if ( post.view ) {
				titleCell.appendChild( el( 'a', { href: post.view, target: '_blank', rel: 'noopener' }, post.title ) );
			} else {
				titleCell.textContent = post.title;
			}

			row.appendChild( titleCell );
			row.appendChild( el( 'td', null, post.type ) );
			row.appendChild( el( 'td', null, post.status ) );
			row.appendChild( el( 'td', { class: 'hc-block-usage-count' }, number( post.count ) ) );

			var actions = el( 'td' );

			if ( post.edit ) {
				actions.appendChild( el( 'a', { href: post.edit, class: 'button button-small', target: '_blank', rel: 'noopener' }, i18n.edit ) );
			}

			row.appendChild( actions );
			tbody.appendChild( row );
		} );

		table.appendChild( tbody );
		section.appendChild( table );

		return section;
	}

	function renderError( site, message ) {
		var notice = el( 'div', { class: 'notice notice-warning inline hc-block-usage-error' } );

		notice.appendChild( el( 'p', null, sprintf( i18n.error, [ site.name || site.url ] ) + ( message ? ' ' + message : '' ) ) );
		results.appendChild( notice );
	}

	function finish() {
		var seconds = Math.max( 1, Math.round( ( Date.now() - state.startedAt ) / 1000 ) );

		state.running = false;
		submit.disabled = false;
		stopBtn.hidden = true;
		progressBar.style.width = '100%';

		progressText.textContent = state.aborted
			? sprintf( i18n.stopped, [ number( state.index ), number( state.sites.length ) ] )
			: sprintf( i18n.done, [ number( state.index ), number( seconds ) ] );

		if ( ! state.instances ) {
			empty.hidden = false;
			empty.textContent = sprintf( i18n.none, [ state.block ] );
			exportForm.hidden = true;
			return;
		}

		exportBlock.value = state.block;
		exportData.value = JSON.stringify( state.rows );
		exportForm.hidden = false;
	}
}());
