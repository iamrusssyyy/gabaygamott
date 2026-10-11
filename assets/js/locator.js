// =====================================================================
// assets/js/locator.js — used by locator/search.php.
//   1. "Use my location" buttons: ask the browser for the visitor's
//      location, then search again sorted by nearest.
//   2. Map view: shows one pin per pharmacy with its price.
//   3. Changing "Sort by" re-runs the search right away.
// =====================================================================

document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('filter-form');

    // -----------------------------------------------------------------
    // 1. "Use my location"
    // -----------------------------------------------------------------
    document.querySelectorAll('[data-use-location]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!navigator.geolocation) {
                alert('Your browser cannot share your location. Choose a city instead.');
                return;
            }
            button.disabled = true;
            button.textContent = 'Finding you…';

            // The browser asks the visitor for permission first.
            navigator.geolocation.getCurrentPosition(
                function (position) {
                    // Round to 3 decimals (about 100 meters) for privacy:
                    // close enough to sort pharmacies, not an exact address.
                    form.querySelector('#lat').value = position.coords.latitude.toFixed(3);
                    form.querySelector('#lng').value = position.coords.longitude.toFixed(3);
                    form.querySelector('#sort').value = 'nearest';
                    form.submit();
                },
                function () {
                    button.disabled = false;
                    button.textContent = '◎ Use my location';
                    alert('We could not get your location. You can choose a city instead.');
                }
            );
        });
    });

    // -----------------------------------------------------------------
    // 3. Re-run the search when "Sort by" changes
    // -----------------------------------------------------------------
    document.querySelectorAll('[data-autosubmit]').forEach(function (field) {
        field.addEventListener('change', function () {
            form.submit();
        });
    });

    // -----------------------------------------------------------------
    // 2. Map view
    // -----------------------------------------------------------------
    const mapBox = document.getElementById('results-map');
    const dataBlock = document.getElementById('map-data');
    if (!mapBox || !dataBlock) {
        return; // this page is in List view
    }

    // The PHP page put the results in a JSON block; turn it back into objects.
    const data = JSON.parse(dataBlock.textContent);

    const map = L.map(mapBox).setView([14.5995, 120.9842], 11); // Metro Manila
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const allPoints = [];

    // Small helper: make an element with some text. Using textContent
    // (not innerHTML) means pharmacy names are always shown as plain text,
    // so they can never run as code.
    function el(tag, text, className) {
        const node = document.createElement(tag);
        if (text) node.textContent = text;
        if (className) node.className = className;
        return node;
    }

    data.pharmacies.forEach(function (pharmacy) {
        // The pin shows the lowest in-stock price, like "₱6.50"
        const label = el('div', pharmacy.min_price === null ? 'Out' : '₱' + pharmacy.min_price.toFixed(2), 'price-pin');
        if (pharmacy.min_price === null) label.classList.add('price-pin-out');
        const icon = L.divIcon({ html: label, className: '', iconSize: null, iconAnchor: [28, 16] });

        // Popup content: name, address, each matching medicine, buttons
        const popup = el('div');
        popup.appendChild(el('strong', pharmacy.name));
        popup.appendChild(el('div', pharmacy.address, 'small text-muted mb-1'));
        pharmacy.items.forEach(function (item) {
            const line = el('div', '', 'small mb-1');
            line.appendChild(el('div', item.name));
            const status = el('span', '● ', 'text-' + item.color);
            line.appendChild(status);
            line.appendChild(document.createTextNode(item.status + ' · ' + item.price + ' · ' + item.updated));
            popup.appendChild(line);
        });
        const buttons = el('div', '', 'd-flex gap-1 mt-1');
        if (pharmacy.phone) {
            const call = el('a', 'Call', 'btn btn-sm btn-outline-secondary');
            call.href = 'tel:' + pharmacy.phone.replace(/[^0-9+]/g, '');
            buttons.appendChild(call);
        }
        const directions = el('a', 'Directions', 'btn btn-sm btn-outline-secondary');
        directions.href = pharmacy.directions;
        directions.target = '_blank';
        directions.rel = 'noopener';
        buttons.appendChild(directions);
        popup.appendChild(buttons);

        L.marker([pharmacy.lat, pharmacy.lng], { icon: icon }).addTo(map).bindPopup(popup);
        allPoints.push([pharmacy.lat, pharmacy.lng]);
    });

    // Show the visitor's own location as a blue dot
    if (data.user) {
        L.circleMarker([data.user.lat, data.user.lng], { radius: 8, color: '#0d6efd', fillOpacity: 0.8 })
            .addTo(map).bindPopup('You are here (approximate)');
        allPoints.push([data.user.lat, data.user.lng]);
    }

    // Zoom the map so every pin fits on screen
    if (allPoints.length > 0) {
        map.fitBounds(allPoints, { padding: [40, 40], maxZoom: 15 });
    }
});
