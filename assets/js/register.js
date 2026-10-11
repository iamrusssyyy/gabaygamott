// =====================================================================
// assets/js/register.js — makes the registration form easier to use:
//   1. Shows only the fields for the account type the user picked.
//   2. Lets a pharmacy click a Leaflet map to pin its location.
//
// The server (register.php) still checks every field, so this file is
// only for convenience — it is NOT the security check.
// =====================================================================

// Run our code after the page's HTML has finished loading.
document.addEventListener('DOMContentLoaded', function () {

    const roleRadios = document.querySelectorAll('input[name="role"]');

    // Every element with data-show-for="..." lists the roles that should
    // see it, e.g. data-show-for="pharmacy partner".
    const roleSections = document.querySelectorAll('[data-show-for]');

    let map = null; // the Leaflet map, created the first time it is needed

    // Returns the value of the checked radio button, or '' if none.
    function selectedRole() {
        const checked = document.querySelector('input[name="role"]:checked');
        return checked ? checked.value : '';
    }

    // Show or hide each section depending on the chosen role.
    function updateSections() {
        const role = selectedRole();

        roleSections.forEach(function (section) {
            const allowedRoles = section.dataset.showFor.split(' ');
            // Bootstrap's "d-none" class means display: none (hidden)
            section.classList.toggle('d-none', !allowedRoles.includes(role));
        });

        if (role === 'pharmacy') {
            showMap();
        }
    }

    roleRadios.forEach(function (radio) {
        radio.addEventListener('change', updateSections);
    });
    updateSections(); // run once now, in case a role was already chosen

    // -----------------------------------------------------------------
    // Map picker (Leaflet + OpenStreetMap)
    // -----------------------------------------------------------------
    function showMap() {
        if (map) {
            // The map was created while hidden; tell Leaflet to re-measure
            // its box now that it is visible, or the tiles look broken.
            map.invalidateSize();
            return;
        }

        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const locationText = document.getElementById('location-text');
        let marker = null;

        // Start the map centered on Metro Manila, zoomed out to see the region.
        map = L.map('pharmacy-map').setView([14.5995, 120.9842], 11);

        // Map images ("tiles") come from OpenStreetMap. The attribution
        // text is required by OpenStreetMap's license.
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Put (or move) the pin and copy its position into the hidden inputs
        // that get sent to the server with the form.
        function placeMarker(latlng) {
            if (marker === null) {
                marker = L.marker(latlng, { draggable: true }).addTo(map);
                marker.on('dragend', function () {
                    placeMarker(marker.getLatLng());
                });
            } else {
                marker.setLatLng(latlng);
            }
            latInput.value = latlng.lat.toFixed(7);
            lngInput.value = latlng.lng.toFixed(7);
            locationText.textContent = 'Pinned at ' + latInput.value + ', ' + lngInput.value;
        }

        // If the form came back with an error, show the pin the user already placed.
        if (latInput.value !== '' && lngInput.value !== '') {
            const saved = L.latLng(parseFloat(latInput.value), parseFloat(lngInput.value));
            placeMarker(saved);
            map.setView(saved, 16);
        }

        map.on('click', function (event) {
            placeMarker(event.latlng);
        });
    }
});
