/**
 * Preserves the vertical scroll position of the category tree module across
 * full page reloads (e.g. after clicking hide/show/move/delete/localize links,
 * which are plain links causing a full navigation, not AJAX).
 *
 * The scrollable element in the TYPO3 backend is not the window itself but
 * the ".t3js-module-body" container (see backend.css: ".module-body{overflow:auto}").
 */
;(() => {
	const STORAGE_KEY = 'itxCategoryTree.scrollY'

	const getScrollContainer = () => document.querySelector('.t3js-module-body')

	const restoreScroll = () => {
		const stored = window.sessionStorage.getItem(STORAGE_KEY)
		if (stored === null) {
			return
		}
		window.sessionStorage.removeItem(STORAGE_KEY)
		const container = getScrollContainer()
		if (container) {
			container.scrollTop = parseInt(stored, 10) || 0
		}
	}

	const rememberScroll = () => {
		const container = getScrollContainer()
		window.sessionStorage.setItem(STORAGE_KEY, String(container ? container.scrollTop : 0))
	}

	document.addEventListener('DOMContentLoaded', () => {
		document.querySelectorAll('.module a[href]').forEach(link => {
			link.addEventListener('click', rememberScroll)
		})
	})

	window.addEventListener('load', () => {
		requestAnimationFrame(() => requestAnimationFrame(restoreScroll))
	})
})()
