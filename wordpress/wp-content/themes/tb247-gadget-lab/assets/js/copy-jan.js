/**
 * Nút "JANをコピー" trên Landing Page: copy mã JAN vào clipboard,
 * đổi label sang "コピーしました" trong 2 giây rồi tự trả lại label ban đầu.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var buttons = document.querySelectorAll( '.tb247-copy-jan-btn' );

	buttons.forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var jan = button.getAttribute( 'data-jan' );
			var labelDefault = button.getAttribute( 'data-label-default' );
			var labelCopied = button.getAttribute( 'data-label-copied' );

			copyText( jan ).then( function () {
				button.textContent = labelCopied;

				window.setTimeout( function () {
					button.textContent = labelDefault;
				}, 2000 );
			} );
		} );
	} );

	/**
	 * Copy text vào clipboard, ưu tiên Clipboard API; fallback textarea ẩn +
	 * execCommand khi trình duyệt cũ, context không secure (http), HOẶC khi
	 * Clipboard API bị trình duyệt từ chối quyền (NotAllowedError) — trường
	 * hợp này Promise.then() thường không đủ, cần .catch() để nút vẫn phản hồi.
	 *
	 * @param {string} text
	 * @return {Promise<void>}
	 */
	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).catch( function () {
				return copyTextFallback( text );
			} );
		}

		return copyTextFallback( text );
	}

	/**
	 * Fallback copy bằng textarea ẩn + execCommand('copy').
	 *
	 * @param {string} text
	 * @return {Promise<void>}
	 */
	function copyTextFallback( text ) {
		return new Promise( function ( resolve ) {
			var textarea = document.createElement( 'textarea' );
			textarea.value = text;
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild( textarea );
			textarea.focus();
			textarea.select();

			try {
				document.execCommand( 'copy' );
			} catch ( err ) {
				// Không làm gì thêm — nút vẫn đổi label để không chặn UX.
			}

			document.body.removeChild( textarea );
			resolve();
		} );
	}
} );
