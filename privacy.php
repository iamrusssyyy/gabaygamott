<?php
// =====================================================================
// privacy.php — Privacy Notice (Data Privacy Act of 2012, RA 10173).
// Linked from the consent checkboxes. Public page (no login needed).
// Have your adviser review this text before the system is used with
// real people.
// =====================================================================

require_once __DIR__ . '/includes/init.php';

$page_title = 'Privacy Notice';
require __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm register-card">
    <div class="card-body p-4">
        <h1 class="h3">Privacy Notice</h1>
        <p class="text-muted">Abiso sa Privacy · Republic Act No. 10173 (Data Privacy Act of 2012)</p>

        <h2 class="h6 mt-4">What we collect</h2>
        <ul>
            <li><strong>All users:</strong> name, email, phone number (optional for most users), city, and password (stored only as a secure hash).</li>
            <li><strong>Beneficiaries:</strong> patient details, the medicine needed, and documents you upload (prescription, certificate of indigency, and an optional valid ID).</li>
            <li><strong>Donors:</strong> details of donated medicines and a photo of the packaging.</li>
            <li><strong>Pharmacies and partners:</strong> business details such as address, map location, and license number.</li>
        </ul>

        <h2 class="h6 mt-4">Why we collect it</h2>
        <p>Only to run GabayGamot: to show where medicines are available, to check and match assistance
            requests with partner programs and donations, and to manage donations safely.</p>

        <h2 class="h6 mt-4">Who can see it</h2>
        <ul>
            <li>Your uploaded documents can be seen only by you, GabayGamot administrators, and the
                partner program your request is matched with.</li>
            <li>Pharmacy names, addresses, map locations, phone numbers, stock, and prices are public.</li>
            <li>We do not sell or share your personal information with anyone else.</li>
        </ul>

        <h2 class="h6 mt-4">How we protect it</h2>
        <p>Passwords are hashed, uploaded documents are stored outside the public website folder, and
            every page checks who you are before showing personal information.</p>

        <h2 class="h6 mt-4">Your rights</h2>
        <p>Under RA 10173 you have the right to be informed, to access your data, to correct it, to
            object to its processing, and to ask for it to be deleted. To use these rights, contact the
            GabayGamot administrator.</p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
