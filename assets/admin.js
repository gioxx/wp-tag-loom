( function ( $ ) {
	'use strict';

	function ajax( data ) {
		return $.post( wptoData.ajaxUrl, $.extend( { nonce: wptoData.nonce }, data ) );
	}

	$( '#wpto-recount-tags' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );

		ajax( { action: 'wpto_recount_tags' } ).done( function () {
			window.location.reload();
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
			$btn.prop( 'disabled', false );
		} );
	} );

	$( '#wpto-test-api-key' ).on( 'click', function () {
		var $btn = $( this );
		var $result = $( '#wpto-test-api-key-result' );
		var apiKey = $( '#wpto_api_key' ).val();

		if ( ! apiKey ) {
			$result.text( wptoData.i18n.enterApiKey );
			return;
		}

		$btn.prop( 'disabled', true );
		$result.text( wptoData.i18n.testingApiKey );

		ajax( { action: 'wpto_test_api_key', api_key: apiKey } ).done( function ( response ) {
			$result.text( response.success ? response.data.message : ( response.data && response.data.message ? response.data.message : wptoData.i18n.error ) );
		} ).fail( function () {
			$result.text( wptoData.i18n.error );
		} ).always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	$( '#wpto-select-all-unused' ).on( 'change', function () {
		$( '.wpto-unused-checkbox' ).prop( 'checked', $( this ).prop( 'checked' ) );
	} );

	$( '#wpto-delete-unused' ).on( 'click', function () {
		var ids = $( '.wpto-unused-checkbox:checked' ).map( function () {
			return $( this ).val();
		} ).get();

		if ( ! ids.length ) {
			return;
		}

		if ( ! window.confirm( wptoData.i18n.confirmDelete ) ) {
			return;
		}

		ajax( { action: 'wpto_delete_unused', term_ids: ids } ).done( function () {
			window.location.reload();
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
		} );
	} );

	var stopRequested = false;

	$( '#wpto-start-analysis' ).on( 'click', function () {
		if ( ! window.confirm( wptoData.i18n.confirmNewAnalysis ) ) {
			return;
		}

		var $btn = $( this );
		$btn.prop( 'disabled', true );
		stopRequested = false;

		ajax( { action: 'wpto_start_analysis' } ).done( function ( response ) {
			if ( response.success ) {
				renderStatus( response.data );
				$( '#wpto-stop-analysis' ).prop( 'disabled', false );
				processTick();
			}
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
			$btn.prop( 'disabled', false );
		} );
	} );

	$( '#wpto-stop-analysis' ).on( 'click', function () {
		stopRequested = true;
		$( this ).prop( 'disabled', true );
		$( '#wpto-current-status' ).text( '' );

		ajax( { action: 'wpto_stop_analysis' } ).done( function ( response ) {
			if ( response.success ) {
				renderStatus( response.data );
				$( '#wpto-start-analysis' ).prop( 'disabled', false );
			}
		} );
	} );

	function processTick() {
		if ( stopRequested ) {
			return;
		}

		$( '#wpto-current-status' ).text( wptoData.i18n.processing );

		ajax( { action: 'wpto_process_tick' } ).done( function ( response ) {
			if ( ! response.success ) {
				window.alert( wptoData.i18n.error );
				return;
			}

			renderStatus( response.data );

			var progress = response.data.progress;

			if ( stopRequested || progress.total === 0 || progress.pending === 0 ) {
				$( '#wpto-current-status' ).text( '' );
				$( '#wpto-start-analysis' ).prop( 'disabled', false );
				$( '#wpto-stop-analysis' ).prop( 'disabled', true );
				if ( progress.total > 0 && progress.pending === 0 ) {
					window.location.reload();
				}
				return;
			}

			processTick();
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
		} );
	}

	function renderStatus( data ) {
		var progress = data.progress;

		$( '#wpto-progress' ).html(
			'<p>' + progress.done + ' / ' + progress.total + '</p>'
		);

		if ( data.log ) {
			var $log = $( '#wpto-log' ).empty();
			data.log.forEach( function ( row ) {
				var text = 'Batch #' + row.id + ' - ' + row.status + ' (' + ( row.processed_at || row.created_at ) + ')';
				if ( row.error_message ) {
					text += ' - ' + row.error_message;
				}
				$( '<li>' ).attr( 'data-batch-id', row.id ).text( text ).appendTo( $log );
			} );
		}
	}

	$( document ).on( 'click', '.wpto-approve', function () {
		if ( ! window.confirm( wptoData.i18n.confirmMerge ) ) {
			return;
		}
		handleSuggestion( $( this ), 'approve' );
	} );

	$( document ).on( 'click', '.wpto-reject', function () {
		handleSuggestion( $( this ), 'reject' );
	} );

	$( document ).on( 'click', '.wpto-restore', function () {
		handleSuggestion( $( this ), 'restore' );
	} );

	$( document ).on( 'change', '.wpto-select-all-suggestions', function () {
		var group = $( this ).data( 'group' );
		$( '.wpto-suggestion-checkbox[data-group="' + group + '"]' ).prop( 'checked', $( this ).prop( 'checked' ) );
		updateBulkButtons( group );
	} );

	$( document ).on( 'change', '.wpto-suggestion-checkbox', function () {
		updateBulkButtons( $( this ).data( 'group' ) );
	} );

	function updateBulkButtons( group ) {
		var anyChecked = $( '.wpto-suggestion-checkbox[data-group="' + group + '"]:checked' ).length > 0;
		$( '.wpto-bulk-approve[data-group="' + group + '"], .wpto-bulk-reject[data-group="' + group + '"], .wpto-bulk-restore[data-group="' + group + '"]' ).prop( 'disabled', ! anyChecked );
	}

	$( document ).on( 'click', '.wpto-bulk-approve', function () {
		handleBulkSuggestion( $( this ), 'approve', wptoData.i18n.confirmBulkApprove );
	} );

	$( document ).on( 'click', '.wpto-bulk-reject', function () {
		handleBulkSuggestion( $( this ), 'reject', wptoData.i18n.confirmBulkReject );
	} );

	$( document ).on( 'click', '.wpto-bulk-restore', function () {
		handleBulkSuggestion( $( this ), 'restore', wptoData.i18n.confirmBulkRestore );
	} );

	function handleBulkSuggestion( $btn, action, confirmMessage ) {
		var group = $btn.data( 'group' );
		var $checkboxes = $( '.wpto-suggestion-checkbox[data-group="' + group + '"]:checked' );
		var ids = $checkboxes.map( function () {
			return $( this ).val();
		} ).get();

		if ( ! ids.length ) {
			window.alert( wptoData.i18n.noneSelected );
			return;
		}

		if ( ! window.confirm( confirmMessage ) ) {
			return;
		}

		var $groupButtons = $( '.wpto-bulk-approve[data-group="' + group + '"], .wpto-bulk-reject[data-group="' + group + '"], .wpto-bulk-restore[data-group="' + group + '"]' );
		$groupButtons.prop( 'disabled', true );

		ajax( { action: 'wpto_bulk_suggestion_action', ids: ids, do: action } ).done( function ( response ) {
			if ( ! response.success ) {
				window.alert( response.data && response.data.message ? response.data.message : wptoData.i18n.error );
				updateBulkButtons( group );
				return;
			}

			var data = response.data;

			$( data.succeeded ).each( function ( i, id ) {
				$( '.wpto-suggestion-checkbox[value="' + id + '"][data-group="' + group + '"]' ).closest( 'tr' ).fadeOut( 200, function () {
					$( this ).remove();
				} );
			} );

			if ( data.failed && Object.keys( data.failed ).length ) {
				var messages = [ wptoData.i18n.bulkFailed ];
				$.each( data.failed, function ( id, message ) {
					messages.push( '#' + id + ': ' + message );
				} );
				window.alert( messages.join( '\n' ) );
			}

			updateBulkButtons( group );
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
			updateBulkButtons( group );
		} );
	}

	function handleSuggestion( $btn, action ) {
		var id = $btn.data( 'id' );
		var $row = $btn.closest( 'tr' );
		$row.find( 'button' ).prop( 'disabled', true );

		var payload = { action: 'wpto_suggestion_action', id: id, do: action };

		if ( 'approve' === action ) {
			payload.target_id = $row.find( '.wpto-target-select' ).val();
		}

		ajax( payload ).done( function ( response ) {
			if ( response.success ) {
				$btn.closest( 'tr' ).fadeOut( 200, function () {
					$( this ).remove();
				} );
			} else {
				window.alert( response.data && response.data.message ? response.data.message : wptoData.i18n.error );
				$btn.closest( 'tr' ).find( 'button' ).prop( 'disabled', false );
			}
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
			$btn.closest( 'tr' ).find( 'button' ).prop( 'disabled', false );
		} );
	}

	$( '#wpto-confirm-merge-form' ).on( 'submit', function ( e ) {
		if ( ! window.confirm( wptoData.i18n.confirmMerge ) ) {
			e.preventDefault();
		}
	} );

	// After Ajax removes/hides the last visible row(s) on a page, the table
	// would otherwise sit empty with stale pagination/counts until the user
	// manually reloads or changes page; reload automatically once nothing
	// tagged "wpto-tag-row-*" is left, so the next page (or "no items") loads.
	function reloadIfTableEmpty() {
		window.setTimeout( function () {
			if ( 0 === $( 'tr[id^="wpto-tag-row-"]' ).length ) {
				window.location.reload();
			}
		}, 250 );
	}

	function updateLockUI( $link, locked ) {
		$link
			.toggleClass( 'wpto-toggle-lock--locked', locked )
			.toggleClass( 'wpto-toggle-lock--unlocked', ! locked )
			.attr( 'title', locked ? wptoData.i18n.unlockThisTag : wptoData.i18n.lockThisTag )
			.attr( 'aria-label', locked ? wptoData.i18n.unlockThisTag : wptoData.i18n.lockThisTag )
			.find( '.dashicons' )
			.toggleClass( 'dashicons-lock', locked )
			.toggleClass( 'dashicons-unlock', ! locked );
	}

	$( document ).on( 'click', '.wpto-toggle-lock', function ( e ) {
		e.preventDefault();

		var $link = $( this );
		var tagId = $link.data( 'id' );

		ajax( { action: 'wpto_toggle_lock', tag_id: tagId } ).done( function ( response ) {
			if ( ! response.success ) {
				window.alert( response.data && response.data.message ? response.data.message : wptoData.i18n.error );
				return;
			}

			var locked = response.data.locked;
			updateLockUI( $link, locked );

			if ( locked && $( '#wpto-hide-locked' ).is( ':checked' ) ) {
				$( '#wpto-tag-row-' + tagId ).fadeOut( 200, function () { $( this ).remove(); } );
				reloadIfTableEmpty();
			}
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
		} );
	} );

	$( document ).on( 'click', '.wpto-delete-tag', function ( e ) {
		e.preventDefault();

		if ( ! window.confirm( wptoData.i18n.confirmDeleteTag ) ) {
			return;
		}

		var tagId = $( this ).data( 'id' );

		ajax( { action: 'wpto_delete_tags', tag_id: [ tagId ] } ).done( function ( response ) {
			if ( ! response.success ) {
				window.alert( response.data && response.data.message ? response.data.message : wptoData.i18n.error );
				return;
			}

			$( '#wpto-tag-row-' + tagId ).fadeOut( 200, function () { $( this ).remove(); } );
			reloadIfTableEmpty();
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
		} );
	} );

	$( '#wpto-tags-filter' ).on( 'submit', function ( e ) {
		var $form = $( this );
		var action = $form.find( 'select[name="action"]' ).val();
		if ( '-1' === action ) {
			action = $form.find( 'select[name="action2"]' ).val();
		}

		if ( 'delete' !== action && 'lock' !== action && 'unlock' !== action ) {
			return;
		}

		e.preventDefault();

		var ids = $form.find( 'input[name="tag_id[]"]:checked' ).map( function () {
			return $( this ).val();
		} ).get();

		if ( ! ids.length ) {
			window.alert( wptoData.i18n.noneSelected );
			return;
		}

		// Resetting the action selects back to "Bulk actions" happens only
		// after the Ajax call resolves, and deferred via setTimeout so it
		// runs after WP core's own submit-time bulk-action validation
		// (wp-admin's common.js, bound to the same form) has already read
		// the selected value — resetting synchronously here made core see
		// "-1" and pop its "Please select a bulk action" alert even though
		// our preventDefault() had already stopped the real submit.
		function resetBulkControls() {
			window.setTimeout( function () {
				$form.find( 'select[name="action"], select[name="action2"]' ).val( '-1' );
				$form.find( 'input[name="tag_id[]"]:checked' ).prop( 'checked', false );
			}, 0 );
		}

		if ( 'delete' === action ) {
			if ( ! window.confirm( wptoData.i18n.confirmDeleteTags ) ) {
				return;
			}

			ajax( { action: 'wpto_delete_tags', tag_id: ids } ).done( function ( response ) {
				if ( ! response.success ) {
					window.alert( response.data && response.data.message ? response.data.message : wptoData.i18n.error );
					return;
				}

				$( response.data.ids ).each( function ( i, id ) {
					$( '#wpto-tag-row-' + id ).fadeOut( 200, function () { $( this ).remove(); } );
				} );
				reloadIfTableEmpty();
				resetBulkControls();
			} ).fail( function () {
				window.alert( wptoData.i18n.error );
			} );
		} else {
			var locked = 'lock' === action;
			var hideLocked = $( '#wpto-hide-locked' ).is( ':checked' );

			ajax( { action: 'wpto_bulk_lock', tag_id: ids, locked: locked ? '1' : '0' } ).done( function ( response ) {
				if ( ! response.success ) {
					window.alert( response.data && response.data.message ? response.data.message : wptoData.i18n.error );
					return;
				}

				$( response.data.ids ).each( function ( i, id ) {
					var $row = $( '#wpto-tag-row-' + id );
					updateLockUI( $row.find( '.wpto-toggle-lock' ), locked );

					if ( locked && hideLocked ) {
						$row.fadeOut( 200, function () { $( this ).remove(); } );
					}
				} );
				if ( locked && hideLocked ) {
					reloadIfTableEmpty();
				}
				resetBulkControls();
			} ).fail( function () {
				window.alert( wptoData.i18n.error );
			} );
		}
	} );

	$( document ).on( 'click', '.wpto-retry-batch', function () {
		var batchId = $( this ).data( 'batch-id' );
		ajax( { action: 'wpto_retry_batch', batch_id: batchId } ).done( function () {
			window.location.reload();
		} );
	} );

	if ( $( '#wpto-progress' ).length ) {
		var pending = parseInt( $( '#wpto-progress' ).data( 'pending' ), 10 ) || 0;
		if ( pending > 0 ) {
			$( '#wpto-start-analysis' ).prop( 'disabled', true );
			$( '#wpto-stop-analysis' ).prop( 'disabled', false );
			processTick();
		}
	}

	$( document ).on( 'click', '.wpto-quick-edit', function ( e ) {
		e.preventDefault();

		var $link = $( this );
		var $row = $( '#wpto-tag-row-' + $link.data( 'id' ) );

		if ( $row.next( '.wpto-quick-edit-row' ).length ) {
			return;
		}

		var colspan = $row.children( 'td, th' ).length;
		var $editRow = $( '<tr class="wpto-quick-edit-row"></tr>' );
		var $cell = $( '<td></td>' ).attr( 'colspan', colspan );

		$cell.append( $( '<p></p>' ).append(
			$( '<label></label>' ).text( wptoData.i18n.quickEditName + ' ' ).append(
				$( '<input type="text" class="wpto-qe-name regular-text" />' ).val( $link.data( 'name' ) )
			)
		) );
		$cell.append( $( '<p></p>' ).append(
			$( '<label></label>' ).text( wptoData.i18n.quickEditSlug + ' ' ).append(
				$( '<input type="text" class="wpto-qe-slug regular-text" />' ).val( $link.data( 'slug' ) )
			)
		) );
		$cell.append(
			$( '<p></p>' )
				.append( $( '<button type="button" class="button button-primary wpto-qe-save"></button>' ).text( wptoData.i18n.quickEditSave ) )
				.append( ' ' )
				.append( $( '<button type="button" class="button wpto-qe-cancel"></button>' ).text( wptoData.i18n.quickEditCancel ) )
				.append( ' ' )
				.append( '<span class="wpto-qe-status"></span>' )
		);

		$editRow.append( $cell );
		$editRow.data( 'tag-id', $link.data( 'id' ) );
		$editRow.data( 'row', $row );
		$editRow.data( 'link', $link );

		$row.hide().after( $editRow );
		$editRow.find( '.wpto-qe-name' ).trigger( 'focus' );
	} );

	$( document ).on( 'click', '.wpto-qe-cancel', function () {
		var $editRow = $( this ).closest( 'tr' );
		$editRow.data( 'row' ).show();
		$editRow.remove();
	} );

	$( document ).on( 'click', '.wpto-qe-save', function () {
		var $editRow = $( this ).closest( 'tr' );
		var $row = $editRow.data( 'row' );
		var $link = $editRow.data( 'link' );
		var tagId = $editRow.data( 'tag-id' );
		var name = $editRow.find( '.wpto-qe-name' ).val();
		var slug = $editRow.find( '.wpto-qe-slug' ).val();
		var $status = $editRow.find( '.wpto-qe-status' );

		$status.text( '' );
		$editRow.find( 'button' ).prop( 'disabled', true );

		ajax( { action: 'wpto_update_tag', tag_id: tagId, name: name, slug: slug } ).done( function ( response ) {
			if ( ! response.success ) {
				$status.text( response.data && response.data.message ? response.data.message : wptoData.i18n.error );
				$editRow.find( 'button' ).prop( 'disabled', false );
				return;
			}

			$row.find( '.column-name strong' ).first().text( response.data.name );
			$row.find( '.column-slug' ).text( response.data.slug );
			$link.data( 'name', response.data.name ).data( 'slug', response.data.slug );

			$row.show();
			$editRow.remove();
		} ).fail( function () {
			$status.text( wptoData.i18n.error );
			$editRow.find( 'button' ).prop( 'disabled', false );
		} );
	} );

	// Comma-separated tag input with the same autocomplete WordPress core
	// uses for the post-edit "Tags" metabox (ajax-tag-search).
	( function () {
		var $input = $( '#wpto-merge-tag-names' );

		if ( ! $input.length || ! $.ui || ! $.ui.autocomplete ) {
			return;
		}

		function split( value ) {
			return value.split( /,\s*/ );
		}

		function extractLast( term ) {
			return split( term ).pop();
		}

		$input
			.on( 'keydown', function ( event ) {
				var isTab   = event.keyCode === $.ui.keyCode.TAB;
				var isEnter = event.keyCode === $.ui.keyCode.ENTER;

				if ( ( isTab || isEnter ) && $( this ).autocomplete( 'instance' ).menu.active ) {
					// A suggestion is highlighted (arrow keys): let the
					// widget confirm it instead of submitting the form.
					event.preventDefault();
				}
			} )
			.autocomplete( {
				minLength: 2,
				source: function ( request, response ) {
					$.get( ajaxurl, {
						action: 'ajax-tag-search',
						tax: 'post_tag',
						q: extractLast( request.term )
					}, function ( data ) {
						response( typeof data === 'string' ? data.split( '\n' ).filter( Boolean ) : data );
					} );
				},
				search: function () {
					return extractLast( this.value ).length >= 2;
				},
				focus: function () {
					return false;
				},
				select: function ( event, ui ) {
					var terms = split( this.value );
					terms.pop();
					terms.push( ui.item.value );
					terms.push( '' );
					this.value = terms.join( ', ' );
					return false;
				}
			} );
	} )();

	// Sticky section nav: smooth-scroll to anchors instead of a hard jump.
	$( '.wpto-section-nav a[href^="#"]' ).on( 'click', function ( event ) {
		var target = $( $( this ).attr( 'href' ) );

		if ( ! target.length ) {
			return;
		}

		event.preventDefault();
		$( 'html, body' ).animate( { scrollTop: target.offset().top - 40 }, 300 );
	} );

	// After adding tag(s) to the merge selection (bulk action or the
	// "Add tags by name" field), scroll down to that section instead of
	// leaving the admin on top of the reloaded page.
	( function () {
		var $target = $( '#wpto-section-merge' );

		if ( $( '.wpto-scroll-to-merge' ).length && $target.length ) {
			$( 'html, body' ).animate( { scrollTop: $target.offset().top - 40 }, 300 );
		}
	} )();

	// Clicking a histogram bar reloads with a bucket filter applied; scroll
	// down to the (now filtered) tag table so the effect is visible.
	( function () {
		var $target = $( '#wpto-section-all-tags' );

		if ( $( '.wpto-histogram-filter-notice' ).length && $target.length ) {
			$( 'html, body' ).animate( { scrollTop: $target.offset().top - 40 }, 300 );
		}
	} )();

	// Back-to-top button: only shown once the page has scrolled a bit.
	( function () {
		var $button = $( '#wpto-back-to-top' );

		if ( ! $button.length ) {
			return;
		}

		$( window ).on( 'scroll', function () {
			$button.toggleClass( 'wpto-visible', $( window ).scrollTop() > 300 );
		} );

		$button.on( 'click', function () {
			$( 'html, body' ).animate( { scrollTop: 0 }, 300 );
		} );
	} )();
} )( jQuery );
