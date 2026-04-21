<?php
/**
 * footer.php — Barangay EQUIAID
 * Reusable site footer component.
 * Include on EVERY page with: <?php include 'footer.php'; ?>
 *
 * CSS required: css/global.css, css/footer.css
 * JS  required: js/footer.js
 */
?>
<footer id="footer" role="contentinfo">
    <div class="container">
        <div class="footer-grid">

            <!-- ── Brand Column ── -->
            <div class="footer-brand">
                <div class="footer-logo-mark" aria-hidden="true">EQ</div>
                <div class="footer-brand-name">Barangay EQUIAID</div>
                <p class="footer-brand-desc">
                    A predictive analysis and decision support system for resource
                    allocation in the DSWD social welfare program, serving Barangay
                    Bagong Silang, Caloocan City.
                </p>
            </div>

            <!-- ── Quick Links ── -->
            <div>
                <div class="footer-col-title">Quick Links</div>
                <nav class="footer-links" aria-label="Footer Navigation">
                    <a href="index.php">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        Home
                    </a>
                    <a href="disaster-map.php">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        Disaster Map
                    </a>
                    <a href="street-status.php">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        Street Status
                    </a>
                    <a href="assistance.php">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        Assistance &amp; Resources
                    </a>
                    <a href="evacuation.php">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        Evacuation Plan
                    </a>
                    <a href="announcements.php">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        Announcements
                    </a>
                    <a href="report.php">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        Report Incident
                    </a>
                </nav>
            </div>

            <!-- ── Contact Info ── -->
            <div>
                <div class="footer-col-title">Contact Us</div>
                <address class="footer-contact" style="font-style:normal;">

                    <div class="footer-contact-item">
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        <span>
                            Barangay Bagong Silang<br>
                            Caloocan City, Metro Manila<br>
                            Philippines
                        </span>
                    </div>

                    <div class="footer-contact-item">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        <span>equiaid@bagongsilang.gov.ph</span>
                    </div>

                    <div class="footer-contact-item hotline">
                        <i class="fa-solid fa-phone" aria-hidden="true"></i>
                        <span>(02) 8-163-000</span>
                    </div>

                </address>
            </div>

        </div><!-- .footer-grid -->

        <!-- ── Bottom Bar ── -->
        <div class="footer-bottom">
            <span>
                &copy; <span id="footer-year"></span> Barangay EQUIAID.
                All rights reserved.
            </span>
            <span>
                <i class="fa-solid fa-shield-halved" aria-hidden="true" style="margin-right:5px;"></i>
                Powered by DSWD &middot; Barangay Bagong Silang
            </span>
        </div>

    </div><!-- .container -->
</footer><!-- #footer -->