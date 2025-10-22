// This file contains shared utility functions for the SPA.

/**
 * Creates and populates a map selector dropdown.
 * @param {string} containerId - The ID of the element to insert the selector into.
 * @param {function} onChangeCallback - The function to call when the selection changes.
 * @returns {Promise<HTMLElement|null>} A promise that resolves with the selector element or null.
 */
async function createMapSelector(containerId, onChangeCallback) {
    const container = document.getElementById(containerId);
    if (!container) return null;

    try {
        const response = await fetch('api.php?action=get_maps');
        const maps = await response.json();

        if (maps.length > 0) {
            container.innerHTML = `
                <label for="mapSelector" class="text-slate-400">Map:</label>
                <select id="mapSelector" class="bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                    ${maps.map(map => `<option value="${map.id}">${map.name}</option>`).join('')}
                </select>
            `;
            const selector = document.getElementById('mapSelector');
            selector.addEventListener('change', () => onChangeCallback(selector.value));
            return selector;
        } else {
            container.innerHTML = `<a href="map.php" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Create a Map</a>`;
            return null;
        }
    } catch (error) {
        console.error("Failed to create map selector:", error);
        container.innerHTML = `<p class="text-red-400">Error loading maps</p>`;
        return null;
    }
}

/**
 * Opens a modal with animation.
 * @param {string} modalId - The ID of the modal element.
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        // Use a timeout to ensure the 'hidden' class is removed before adding 'is-open'
        setTimeout(() => modal.classList.add('is-open'), 10);
    }
}

/**
 * Closes a modal with animation.
 * @param {string} modalId - The ID of the modal element.
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('is-open');
        // Listen for the transition to end before adding 'hidden'
        const onTransitionEnd = () => {
            modal.classList.add('hidden');
            modal.removeEventListener('transitionend', onTransitionEnd);
        };
        modal.addEventListener('transitionend', onTransitionEnd);
    }
}