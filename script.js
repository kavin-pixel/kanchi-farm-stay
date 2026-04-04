/**
 * Kanchi Farm Stay - Main Script
 * Handles data, navigation, and page logic
 */

// DATA: Rooms
const rooms = [
    {
        id: 'wooden-villa',
        name: "Wooden Villa",
        shortDescription: "Unique rooftop villa with panoramic views and private terrace.",
        fullDescription: "Perched on the top floor, the Wooden Villa offers a unique stay experience. This 'penthouse' style accommodation features rustic wooden architecture and opens onto a private terrace with sweeping views of the farm.",
        amenities: ["Private Terrace", "Panoramic View", "Rustic Interiors", "Queen Bed", "Outdoor Seating"],
        image: "assets/images/wooden-villa-swing-3.jpg",
        images: [
            "assets/images/wooden-villa-swing-3.jpg",
            "assets/images/wooden-villa-swing-4.jpg",
            "assets/images/wooden-villa-swing-5.jpg",
            "assets/images/gallery-room-swing.jpg",
            "assets/images/wooden-villa-bathroom.png"
        ],
        price: "₹3,500 / night",
        numericPrice: 3500,
        capacity: "3 Adults, 1 Child",
        size: "512 sqft",
        airbnbUrl: "https://airbnb.co.in/h/kanchifarmstay-woodenvilla",
        bookingUrl: "https://www.booking.com/Share-m1A8St"
    },
    {
        id: 'white-villa',
        name: "White Villa",
        shortDescription: "A luxurious white-themed villa offering elegance and serenity.",
        fullDescription: "The White Villa stands as a beacon of elegance amidst the green farm. With its pristine white architecture and spacious interiors, it offers a luxurious stay for those who appreciate style and comfort.",
        amenities: ["King Size Bed", "Private Entrance", "Modern Interiors", "Air Conditioning", "Large Windows"],
        image: "assets/images/white-villa-main-hero.jpg",
        images: [
            "assets/images/white-villa-main-hero.jpg",
            "assets/images/white-villa-1.jpg",
            "assets/images/white-villa-2.jpg",
            "assets/images/white-villa-3.jpg",
            "assets/images/white-villa-4.jpg"
        ],
        price: "₹2,500 / night",
        numericPrice: 2500,
        capacity: "4 Adults, 2 Children",
        size: "600 sqft",
        airbnbUrl: "https://airbnb.co.in/h/kanchifarmstay-whitevilla",
        bookingUrl: "https://www.booking.com/Share-HedGP2U"
    },
    {
        id: 'natures-nest',
        name: "Nature's Nest",
        shortDescription: "A cozy retreat offering a blend of rustic charm and modern comfort.",
        fullDescription: "Nature's Nest is perfect for couples or small families. Featuring a comfortable queen-sized bed and views of the surrounding greenery, it offers a peaceful respite from the bustle of city life.",
        amenities: ["Queen Size Bed", "Garden View", "Air Conditioning", "Wi-Fi", "Attached Bathroom"],
        image: "assets/images/natures-nest-main.jpg",
        images: [
            "assets/images/natures-nest-main.jpg",
            "assets/images/natures-nest-1.jpg",
            "assets/images/natures-nest-2.jpg",
            "assets/images/natures-nest-3.jpg"
        ],
        price: "₹2,500 / night",
        numericPrice: 2500,
        capacity: "4 Adults, 2 Children",
        size: "300 sqft",
        airbnbUrl: "https://airbnb.co.in/h/kanchifarmstay-naturesnest",
        bookingUrl: "https://www.booking.com/Share-ckOtkG"
    },
    {
        id: 'tranquil-retreat',
        name: "Tranquil Retreat",
        shortDescription: "Spacious suite in the main farmhouse with access to verandas.",
        fullDescription: "The Tranquil Retreat suite offers an expansive layout within the main farmhouse structure. It features premium furnishings, a king-sized bed, and direct access to the breezy verandas.",
        amenities: ["King Size Bed", "Spacious Living Area", "Work Desk", "Air Conditioning", "Veranda Access"],
        image: "assets/images/tranquil-retreat-main.jpg",
        images: [
            "assets/images/tranquil-retreat-main.jpg",
            "assets/images/tranquil-retreat-1.jpg",
            "assets/images/tranquil-retreat-2.jpg",
            "assets/images/tranquil-retreat-3.jpg",
            "assets/images/tranquil-retreat-4.jpg"
        ],
        price: "₹2,500 / night",
        numericPrice: 2500,
        capacity: "4 Adults, 2 Children",
        size: "450 sqft",
        airbnbUrl: "https://airbnb.co.in/h/kanchifarmstay-tranquilretreat",
        bookingUrl: "https://www.booking.com/Share-ywNmBh"
    },
    {
        id: 'wooden-cottage',
        name: "Wooden Cottage",
        shortDescription: "Get away from it all when you stay under the stars.",
        fullDescription: "A group of friends created this place as an escape from buzzing city life. A perfect peaceful getaway without gadgets with friends and family. Features a private pool and farm stay setting.",
        amenities: ["Kitchen", "Wifi", "Private Pool", "Air Conditioning", "Pet Friendly", "BBQ Grill"],
        image: "assets/images/wooden-cottage-hero.jpg",
        images: [
            "assets/images/wooden-cottage-hero.jpg",
            "assets/images/wooden-cottage-1.jpg",
            "assets/images/wooden-cottage-2.jpg",
            "assets/images/wooden-cottage-3.jpg"
        ],
        price: "₹2,000 / night",
        numericPrice: 2000,
        capacity: "4 Guests, 1 Bedroom, 2 Beds",
        size: "Contact for details",
        airbnbUrl: "https://airbnb.co.in/h/kanchifarmstay-woodencottage",
        bookingUrl: "index.html#contact"
    }
];

// DATA: Reviews
const reviews = [
    {
        id: 1,
        name: "Dipesh Shaw",
        rating: 5,
        text: "Recently, I went to Kanchi Farm Stay with my family, and we had a very nice experience there. Their hospitality, politeness, and the way they welcomed us were excellent. They cooked delicious food for us, and my entire family truly had a wonderful time there. The way the farm has been developed gives you a very serene feeling.",
        date: "January 2026",
        platform: "google",
        room: "Kanchi Farm Stay"
    },
    {
        id: 2,
        name: "Angela",
        rating: 5,
        text: "This is a beautiful place, surrounded by trees and countryside. We were well looked after in this beautiful place. I was sorry to leave.",
        date: "11 January 2026",
        platform: "booking",
        room: "Wooden Villa"
    },
    {
        id: 3,
        name: "Jayanthi Gurureddy",
        rating: 5,
        text: "Amazing home stay with super food. Highly recommend for family to spend quality time here.",
        date: "January 2026",
        platform: "google",
        room: "Kanchi Farm Stay"
    },
    {
        id: 4,
        name: "Rino",
        rating: 5,
        text: "One of the best stays you can find around Kanchipuram. A wonderful host, delicious and homely food, and a beautiful, well-maintained property. The place is very peaceful and calm, making it perfect for a relaxing getaway.",
        date: "28 December 2025",
        platform: "booking",
        room: "Wooden Villa"
    },
    {
        id: 5,
        name: "Ganesh Devaraj",
        rating: 5,
        text: "Very good and silent place. One can stay there for peace of mind with nature.",
        date: "January 2026",
        platform: "google",
        room: "Kanchi Farm Stay"
    }
];

// COMPONENTS: Navbar
function renderNavbar() {
    const navbarHTML = `
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="index.html" class="navbar-logo">
                <img src="assets/images/logo.png" alt="Kanchi Farm Stay" class="logo-img" onerror="this.innerText='Kanchi Farm Stay'">
                <span style="display:none">Kanchi Farm Stay</span>
            </a>
            <div class="navbar-toggle" id="navbar-toggle" onclick="toggleMenu()">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
            <div class="navbar-links" id="navbar-links">
                <a href="index.html" class="nav-link ${isActive('index.html')}">Home</a>
                <a href="gallery.html" class="nav-link ${isActive('gallery.html')}">Gallery</a>
                <a href="accommodations.html" class="nav-link ${isActive('accommodations.html')}">Accommodations</a>
                <a href="reviews.html" class="nav-link ${isActive('reviews.html')}">Reviews</a>
                <a href="contact.html" class="nav-link ${isActive('contact.html')}">Contact Us</a>
                <a href="accommodations.html" class="booking-btn btn" style="color:white;text-decoration:none;">Book Now</a>
            </div>
        </div>
    </nav>
    `;
    const headerElement = document.getElementById('navbar-placeholder');
    if (headerElement) headerElement.innerHTML = navbarHTML;
}

// COMPONENTS: Footer
function renderFooter() {
    const footerHTML = `
    <footer class="footer">
        <div class="container footer-container">
            <div class="footer-col">
                <h3>Kanchi Farm Stay</h3>
                <p>Experience the serenity of rural life with modern comforts. A perfect getaway for tranquility and nature lovers.</p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.html">Home</a></li>
                    <li><a href="accommodations.html">Accommodations</a></li>
                    <li><a href="index.html#activities">Activities</a></li>
                    <li><a href="gallery.html">Gallery</a></li>
                    <li><a href="reviews.html">Reviews</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contact</h4>
                <p>📍 704, Sastha Nagar, Kovil Street, Chithathur, Tiruvannamalai, Tamil Nadu</p>
                <p>📞 +91 6383726094</p>
                <p>📞 +91 9028001639</p>
                <p>📞 +91 8825775747</p>
                <p>📧 ops@kanchifarmstay.com</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; ${new Date().getFullYear()} Kanchi Farm Stay. All rights reserved.</p>
        </div>
    </footer>
    `;
    const footerElement = document.getElementById('footer-placeholder');
    if (footerElement) footerElement.innerHTML = footerHTML;
}

// LOGIC: Mobile Menu
function toggleMenu() {
    const links = document.getElementById('navbar-links');
    links.classList.toggle('active');
}

// LOGIC: Active Link Helper
function isActive(page) {
    const path = window.location.pathname;
    const pageName = path.split('/').pop() || 'index.html';
    // Match with or without .html extension
    const pageBase = page.replace(/\.html$/, '');
    const nameBase = pageName.replace(/\.html$/, '') || 'index';
    return (nameBase === pageBase || pageName === page) ? 'active' : '';
}

// LOGIC: Room Details Page
function loadRoomDetails() {
    const params = new URLSearchParams(window.location.search);
    const roomId = params.get('id');
    const room = rooms.find(r => r.id === roomId);

    if (room) {
        // Hero Image / Slider
        const heroImg = document.getElementById('room-hero-img');
        if (heroImg) heroImg.src = room.image;
        if (room.images && room.images.length > 1) initRoomSlider(room.images);

        // Title
        const title = document.getElementById('room-title');
        if (title) title.innerText = room.name;

        // Full Description
        const desc = document.getElementById('room-full-desc');
        if (desc) desc.innerText = room.fullDescription;

        // Price
        const price = document.getElementById('room-price');
        if (price) price.innerText = room.price;

        // Capacity
        const cap = document.getElementById('room-capacity');
        if (cap) cap.innerText = room.capacity;

        // Size
        const size = document.getElementById('room-size');
        if (size) size.innerText = room.size;

        // Amenities
        const amenitiesList = document.getElementById('room-amenities');
        if (amenitiesList) {
            amenitiesList.innerHTML = room.amenities.map(item => `<li>${item}</li>`).join('');
        }

        // Gallery
        const galleryGrid = document.getElementById('room-gallery-grid');
        if (galleryGrid) {
            galleryGrid.innerHTML = room.images.map(img => `
                <div class="gallery-item">
                    <img src="${img}" alt="${room.name}">
                </div>
            `).join('');
        }

        // Booking Links
        const airbnbBtn = document.getElementById('airbnb-btn');
        if (airbnbBtn) airbnbBtn.href = room.airbnbUrl;

        const bookingBtn = document.getElementById('booking-btn');
        if (bookingBtn) bookingBtn.href = room.bookingUrl;

        // Load availability calendar
        loadAvailabilityCalendar(room.id);

        // Razorpay Date pickers and dynamic price
        const checkinInput = document.getElementById('room-checkin');
        const checkoutInput = document.getElementById('room-checkout');
        const totalPriceEl = document.getElementById('room-total-price');
        const razorpayBtn = document.getElementById('razorpay-btn');

        if (checkinInput && checkoutInput && totalPriceEl && razorpayBtn) {
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);

            checkinInput.min = today.toISOString().split('T')[0];
            checkoutInput.min = tomorrow.toISOString().split('T')[0];

            let days = 1;
            let currentTotal = room.numericPrice;

            const updateTotalPrice = () => {
                const checkinDate = new Date(checkinInput.value);
                const checkoutDate = new Date(checkoutInput.value);

                if (checkinDate && checkoutDate && checkoutDate > checkinDate) {
                    const diffTime = Math.abs(checkoutDate - checkinDate);
                    days = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    currentTotal = room.numericPrice * days;
                    totalPriceEl.innerText = `₹${currentTotal.toLocaleString()}`;
                    razorpayBtn.disabled = false;
                    razorpayBtn.style.opacity = "1";
                    razorpayBtn.style.cursor = "pointer";
                } else {
                    totalPriceEl.innerText = 'Invalid Dates';
                    razorpayBtn.disabled = true;
                    razorpayBtn.style.opacity = "0.5";
                    razorpayBtn.style.cursor = "not-allowed";
                    days = 0;
                    currentTotal = 0;
                }
            };

            checkinInput.addEventListener('change', () => {
                const newMin = new Date(checkinInput.value);
                newMin.setDate(newMin.getDate() + 1);
                checkoutInput.min = newMin.toISOString().split('T')[0];
                if (new Date(checkoutInput.value) <= new Date(checkinInput.value)) {
                    checkoutInput.value = newMin.toISOString().split('T')[0];
                }
                updateTotalPrice();
            });

            checkoutInput.addEventListener('change', updateTotalPrice);

            // Set Initial Values
            checkinInput.value = today.toISOString().split('T')[0];
            checkoutInput.value = tomorrow.toISOString().split('T')[0];
            updateTotalPrice();

            // Razorpay logic
            razorpayBtn.addEventListener('click', async (e) => {
                e.preventDefault();

                // Validate Guest Details
                const guestName = document.getElementById('guest-name')?.value.trim();
                const guestEmail = document.getElementById('guest-email')?.value.trim();
                const guestPhone = document.getElementById('guest-phone')?.value.trim();

                if (!guestName || !guestEmail || !guestPhone) {
                    alert('Please fill in your Full Name, Email Address, and Phone Number before proceeding to payment.');
                    return;
                }

                if (days <= 0) {
                    alert('Please select valid Check-in and Check-out dates.');
                    return;
                }

                const amountInPaise = currentTotal * 100;

                // Disable button and show loading state
                const originalText = razorpayBtn.innerText;
                razorpayBtn.innerText = "Processing...";
                razorpayBtn.disabled = true;

                try {
                    // 1. Create an Order on the server
                    const response = await fetch('create_order.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            amount: amountInPaise,
                            guestName: guestName,
                            guestEmail: guestEmail,
                            guestPhone: guestPhone,
                            checkin: checkinInput.value,
                            checkout: checkoutInput.value,
                            roomName: room.name,
                            days: days
                        })
                    });

                    const orderData = await response.json();

                    if (!response.ok || !orderData.id) {
                        throw new Error(orderData.error || orderData.error_description || "Failed to create order");
                    }

                    // 2. Initialize Razorpay Checkout with the Order ID
                    const options = {
                        key: "rzp_live_SImDeaehZI93nG",
                        amount: amountInPaise,
                        currency: "INR",
                        name: "Kanchi Farm Stay",
                        description: `Payment for ${room.name} (${days} night${days > 1 ? 's' : ''})`,
                        image: window.location.origin + "/assets/images/logo.png",
                        order_id: orderData.id,
                        handler: function (response) {
                            // Confirm booking in database and trigger WhatsApp notification
                            fetch('confirm_booking.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    paymentId: response.razorpay_payment_id,
                                    orderId:   response.razorpay_order_id,
                                    roomId:    room.id,
                                    roomName:  room.name,
                                    checkIn:   checkinInput.value,
                                    checkOut:  checkoutInput.value,
                                    guestName: guestName,
                                    guestEmail: guestEmail,
                                    guestPhone: guestPhone,
                                    amount:    currentTotal,
                                    days:      days
                                })
                            }).catch(() => {}); // fire-and-forget
                            alert(`Payment Successful!\nPayment ID: ${response.razorpay_payment_id}\nRoom: ${room.name}\nCheck-in: ${checkinInput.value}\nCheck-out: ${checkoutInput.value}\nGuest: ${guestName}\nThank you for booking with Kanchi Farm Stay!`);
                            loadAvailabilityCalendar(room.id); // refresh calendar
                        },
                        prefill: {
                            name: guestName,
                            email: guestEmail,
                            contact: guestPhone
                        },
                        notes: {
                            room_id: room.id,
                            checkin: checkinInput.value,
                            checkout: checkoutInput.value,
                            days: days
                        },
                        theme: {
                            color: "#6B8E23" // matching the theme's green
                        }
                    };

                    const rzp1 = new window.Razorpay(options);
                    rzp1.on('payment.failed', function (response) {
                        alert("Payment Failed. Reason: " + response.error.description);
                    });

                    // Re-enable on close
                    rzp1.on('modal.closed', function () {
                        razorpayBtn.innerText = originalText;
                        razorpayBtn.disabled = false;
                    });

                    rzp1.open();

                } catch (error) {
                    console.error("Order creation error:", error);
                    alert("Unable to reach payment server. " + error.message);
                    razorpayBtn.innerText = originalText;
                    razorpayBtn.disabled = false;
                }
            });
        }

    } else {
        // Handle not found
        document.body.innerHTML = '<div class="container section text-center"><h1>Room Not Found</h1><a href="accommodations.html" class="btn">View All Rooms</a></div>';
    }
}

// LOGIC: Reviews Page
let currentPlatformFilter = 'all';

function loadReviews() {
    const grid = document.getElementById('reviews-grid');
    if (!grid) return;

    // Filtering logic
    const filteredReviews = currentPlatformFilter === 'all'
        ? reviews
        : reviews.filter(r => r.platform === currentPlatformFilter);

    const reviewsHTML = filteredReviews.map(review => `
        <div class="review-card">
            <div class="review-header">
                <div class="reviewer-info">
                   <div class="reviewer-avatar">${review.name.charAt(0)}</div>
                   <div>
                       <h3 class="reviewer-name">${review.name}</h3>
                       <p class="review-date">${review.date}</p>
                   </div>
                </div>
                <div class="platform-badge" style="background-color: ${getPlatformColor(review.platform)}">
                    ${review.platform === 'airbnb' ? 'Airbnb' : review.platform === 'booking' ? 'Booking.com' : 'Google'}
                </div>
            </div>
            <div class="review-rating">
                <div class="stars">★★★★★</div>
                <span class="room-tag">${review.room || 'Stay'}</span>
            </div>
            <p class="review-text">"${review.text}"</p>
        </div>
    `).join('');

    if (filteredReviews.length === 0) {
        grid.innerHTML = '<p class="no-reviews">No reviews found for this filter.</p>';
    } else {
        grid.innerHTML = reviewsHTML;
    }
}

function filterReviews(platform) {
    currentPlatformFilter = platform;
    loadReviews();

    const buttons = document.querySelectorAll('.filter-btn');
    buttons.forEach(btn => {
        // Remove active class
        btn.classList.remove('active');
        // Add active class if onclick matches
        if (btn.getAttribute('onclick').includes(`'${platform}'`)) {
            btn.classList.add('active');
        }
    });
}

function getPlatformColor(platform) {
    if (platform === 'airbnb') return '#FF5A5F';
    if (platform === 'booking') return '#003580';
    return '#4285F4'; // Google Blue
}

// LOGIC: Home Page Reviews
// Reverting to static HTML in index.html for home page reviews as per user request

// DATA: Gallery Images
const galleryImages = [
    { src: 'assets/images/wooden-villa-swing-3.jpg', caption: 'Wooden Villa Beds', category: 'accommodations' },
    { src: 'assets/images/wooden-villa-swing-4.jpg', caption: 'Wooden Villa Living Area', category: 'accommodations' },
    { src: 'assets/images/wooden-villa-swing-5.jpg', caption: 'Wooden Villa Room Frame', category: 'accommodations' },
    { src: 'assets/images/wooden-villa-bathroom.png', caption: 'Wooden Villa Bathroom', category: 'accommodations' },
    { src: 'assets/images/farm-aerial.jpg', caption: 'Aerial View of Kanchi Farm Stay', category: 'farm-life' },
    { src: 'assets/images/villa-exterior.jpg', caption: 'Villa Exterior', category: 'accommodations' },
    { src: 'assets/images/farm-pathway.jpg', caption: 'Farm Pathway with Palm Trees', category: 'farm-life' },
    { src: 'assets/images/farm-swing.jpg', caption: 'Swing and Play Area', category: 'facilities' },
    { src: 'assets/images/outdoor-seating.jpg', caption: 'Outdoor Meditation Seating', category: 'facilities' },
    { src: 'assets/images/farm-rabbit-sunset.jpg', caption: 'Farm Rabbit at Sunset', category: 'animals' },
    { src: 'assets/images/farm-rabbit-closeup.jpg', caption: 'Rabbit Close-up', category: 'animals' },
    { src: 'assets/images/natures-nest-gate.jpg', caption: "Nature's Nest Gate", category: 'farm-life' },
    { src: 'assets/images/tranquil-retreat-gate.jpg', caption: 'Tranquil Retreat Gate', category: 'farm-life' },
    { src: 'assets/images/farm-turkeys.jpg', caption: 'Farm Turkeys', category: 'animals' },
    { src: 'assets/images/farm-rabbits-pair.jpg', caption: 'Pair of Rabbits', category: 'animals' },
    { src: 'assets/images/farm-chicks-group.jpg', caption: 'Baby Chicks', category: 'animals' },
    { src: 'assets/images/farm-rabbit-white.jpg', caption: 'White Rabbit', category: 'animals' },
    { src: 'assets/images/farm-rabbits-feeding.jpg', caption: 'Rabbits Feeding', category: 'animals' },
    { src: 'assets/images/farm-rice-paddy.jpg', caption: 'Rice Paddy Fields', category: 'farm-life' },
    { src: 'assets/images/farm-dog.jpg', caption: 'Farm Dog', category: 'animals' },
    { src: 'assets/images/farm-chicks-enclosure.jpg', caption: 'Chicks in Enclosure', category: 'animals' },
    { src: 'assets/images/farm-chick-closeup.jpg', caption: 'Baby Chick Close-up', category: 'animals' },
    { src: 'assets/images/farm-gate.jpg', caption: 'Welcome to Kanchi Farm Stay', category: 'farm-life' },
    { src: 'assets/images/farm-exterior.jpg', caption: 'Lush Green Surroundings', category: 'farm-life' },
    { src: 'assets/images/natures-nest-1.jpg', caption: 'Spacious Wooden Interiors', category: 'accommodations' },
    { src: 'assets/images/natures-nest-2.jpg', caption: 'Cozy Living Spaces', category: 'accommodations' },
    { src: 'assets/images/about-farm.jpg', caption: 'Rustic Cottage Charm', category: 'farm-life' },
    { src: 'assets/images/farm-dog-portrait.jpg', caption: 'German Shepherd Portrait', category: 'animals' },
    { src: 'assets/images/farm-dogs-relaxing.jpg', caption: 'Farm Dogs Relaxing', category: 'animals' },
    { src: 'assets/images/gallery-room-swing.jpg', caption: 'Living Room with Swing', category: 'accommodations' },
    { src: 'assets/images/gallery-white-building-front.jpg', caption: 'Farm House Front View', category: 'farm-life' },
    { src: 'assets/images/gallery-small-cottage.jpg', caption: 'Small Cottage', category: 'accommodations' },
    { src: 'assets/images/gallery-white-building-side.jpg', caption: 'Farm House Side View', category: 'farm-life' },
    { src: 'assets/images/gallery-pink-building.jpg', caption: 'Pink Farm Building', category: 'farm-life' },
    { src: 'assets/images/gallery-dining-hall-1.jpg', caption: 'Spacious Dining Hall', category: 'facilities' },
    { src: 'assets/images/gallery-dining-hall-2.jpg', caption: 'Dining Area', category: 'facilities' },
    { src: 'assets/images/gallery-entrance-car.jpg', caption: 'Farm Entrance', category: 'farm-life' },
    { src: 'assets/images/gallery-entrance-path.jpg', caption: 'Entrance Pathway', category: 'farm-life' },
    { src: 'assets/images/gallery-tent-camping.jpg', caption: 'Camping Tent', category: 'facilities' },
];

// LOGIC: Gallery Page
let currentGalleryFilter = 'all';
let currentLightboxImages = [];
let currentLightboxIndex = 0;

function renderGalleryGrid(filter) {
    currentGalleryFilter = filter;
    const grid = document.getElementById('gallery-grid');
    if (!grid) return;

    const filtered = filter === 'all' ? galleryImages : galleryImages.filter(img => img.category === filter);
    currentLightboxImages = filtered;

    grid.innerHTML = filtered.map((img, index) => `
        <div class="gallery-item" onclick="openLightbox(${index})">
            <img src="${img.src}" alt="${img.caption}" loading="lazy">
            <div class="image-overlay"><span>${img.caption}</span></div>
        </div>
    `).join('');

    // Update active filter button
    document.querySelectorAll('.gallery-filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === filter);
    });
}

// LOGIC: Lightbox
function initLightbox() {
    const lightbox = document.createElement('div');
    lightbox.id = 'lightbox';
    lightbox.className = 'lightbox';

    const img = document.createElement('img');
    img.id = 'lightbox-img';

    const closeBtn = document.createElement('div');
    closeBtn.className = 'lightbox-close';
    closeBtn.innerHTML = '&times;';

    const prevBtn = document.createElement('div');
    prevBtn.className = 'lightbox-prev';
    prevBtn.innerHTML = '&#8249;';
    prevBtn.addEventListener('click', (e) => { e.stopPropagation(); navigateLightbox(-1); });

    const nextBtn = document.createElement('div');
    nextBtn.className = 'lightbox-next';
    nextBtn.innerHTML = '&#8250;';
    nextBtn.addEventListener('click', (e) => { e.stopPropagation(); navigateLightbox(1); });

    const caption = document.createElement('div');
    caption.className = 'lightbox-caption';
    caption.id = 'lightbox-caption';

    const counter = document.createElement('div');
    counter.className = 'lightbox-counter';
    counter.id = 'lightbox-counter';

    lightbox.appendChild(prevBtn);
    lightbox.appendChild(img);
    lightbox.appendChild(nextBtn);
    lightbox.appendChild(closeBtn);
    lightbox.appendChild(caption);
    lightbox.appendChild(counter);
    document.body.appendChild(lightbox);

    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) closeLightbox();
    });

    closeBtn.addEventListener('click', closeLightbox);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') navigateLightbox(-1);
        if (e.key === 'ArrowRight') navigateLightbox(1);
    });
}

function openLightbox(indexOrSrc) {
    const lightbox = document.getElementById('lightbox');
    const img = document.getElementById('lightbox-img');
    const captionEl = document.getElementById('lightbox-caption');
    const counterEl = document.getElementById('lightbox-counter');
    if (!lightbox || !img) return;

    if (typeof indexOrSrc === 'number') {
        currentLightboxIndex = indexOrSrc;
        // If no filtered set (e.g. called from room details), fall back to full list
        if (!currentLightboxImages.length) currentLightboxImages = galleryImages;
        const item = currentLightboxImages[currentLightboxIndex];
        img.src = item.src;
        if (captionEl) captionEl.textContent = item.caption;
        if (counterEl) counterEl.textContent = `${currentLightboxIndex + 1} / ${currentLightboxImages.length}`;
    } else {
        // Direct src string (e.g. from room details page)
        img.src = indexOrSrc;
        if (captionEl) captionEl.textContent = '';
        if (counterEl) counterEl.textContent = '';
        currentLightboxImages = [];
    }

    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function navigateLightbox(direction) {
    if (!currentLightboxImages.length) return;
    currentLightboxIndex = (currentLightboxIndex + direction + currentLightboxImages.length) % currentLightboxImages.length;
    openLightbox(currentLightboxIndex);
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    if (lightbox) {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// INITIALIZATION
document.addEventListener('DOMContentLoaded', () => {
    renderNavbar();
    renderFooter();
    initLightbox();

    // Page specific logic — match with or without .html extension (clean URLs)
    const path = window.location.pathname.replace(/\.html$/, '');

    if (path.includes('room-details')) {
        loadRoomDetails();
    }

    if (path.includes('reviews')) {
        loadReviews();
    }

    if (path.includes('accommodations')) {
        renderAccommodations();
    }

    if (path.includes('gallery')) {
        renderGalleryGrid('all');
    }

    renderWhatsAppButton();
});

// COMPONENT: Floating WhatsApp Button
function renderWhatsAppButton() {
    const a = document.createElement('a');
    a.href = 'https://wa.me/916383726094';
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    a.className = 'whatsapp-float';
    a.setAttribute('aria-label', 'Chat on WhatsApp');
    a.innerHTML = `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>`;
    document.body.appendChild(a);
}

// LOGIC: Room Image Slider
function initRoomSlider(images) {
    const hero = document.querySelector('.room-hero');
    if (!hero || !images || images.length <= 1) return;

    // Build slider
    const slider = document.createElement('div');
    slider.className = 'room-hero-slider';
    slider.id = 'room-hero-slider';

    images.forEach(src => {
        const img = document.createElement('img');
        img.src = src;
        img.className = 'room-hero-slide';
        img.alt = 'Room photo';
        slider.appendChild(img);
    });

    // Replace the static hero img
    const existingImg = hero.querySelector('#room-hero-img');
    if (existingImg) existingImg.replaceWith(slider);

    // Prev / Next buttons
    const prevBtn = document.createElement('button');
    prevBtn.className = 'slider-btn slider-btn-prev';
    prevBtn.innerHTML = '&#8249;';
    prevBtn.setAttribute('aria-label', 'Previous photo');

    const nextBtn = document.createElement('button');
    nextBtn.className = 'slider-btn slider-btn-next';
    nextBtn.innerHTML = '&#8250;';
    nextBtn.setAttribute('aria-label', 'Next photo');

    // Dots
    const dotsEl = document.createElement('div');
    dotsEl.className = 'slider-dots';
    images.forEach((_, i) => {
        const dot = document.createElement('button');
        dot.className = 'slider-dot' + (i === 0 ? ' active' : '');
        dot.setAttribute('aria-label', `Go to photo ${i + 1}`);
        dot.addEventListener('click', () => goToSlide(i));
        dotsEl.appendChild(dot);
    });

    hero.appendChild(prevBtn);
    hero.appendChild(nextBtn);
    hero.appendChild(dotsEl);

    let current = 0;

    function goToSlide(n) {
        current = (n + images.length) % images.length;
        slider.style.transform = `translateX(-${current * 100}%)`;
        dotsEl.querySelectorAll('.slider-dot').forEach((d, i) => {
            d.classList.toggle('active', i === current);
        });
    }

    prevBtn.addEventListener('click', () => goToSlide(current - 1));
    nextBtn.addEventListener('click', () => goToSlide(current + 1));

    // Auto-advance every 5s
    setInterval(() => goToSlide(current + 1), 5000);
}

// LOGIC: Availability Calendar (room-details page)
function loadAvailabilityCalendar(roomId) {
    const container = document.getElementById('availability-calendar');
    if (!container) return;

    fetch(`channel-manager/availability-api.php?room=${roomId}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) { container.innerHTML = ''; return; }
            renderAvailabilityCalendar(container, data.blocked || []);
        })
        .catch(() => { container.innerHTML = ''; });
}

function renderAvailabilityCalendar(container, blockedRanges) {
    // Build a Set of blocked date strings for fast lookup
    function buildBlockedSet(ranges) {
        const set = new Set();
        ranges.forEach(([ci, co]) => {
            let cur = new Date(ci + 'T00:00:00');
            const end = new Date(co + 'T00:00:00');
            while (cur < end) {
                set.add(cur.toISOString().split('T')[0]);
                cur.setDate(cur.getDate() + 1);
            }
        });
        return set;
    }

    const blocked = buildBlockedSet(blockedRanges);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    let html = `
      <div class="avail-cal-wrap">
        <h3 style="margin-bottom:1rem;font-size:1rem;">Availability</h3>
        <div class="avail-legend">
          <span class="avail-dot avail-free"></span> Available
          <span class="avail-dot avail-blocked" style="margin-left:1rem;"></span> Booked
        </div>
    `;

    // Show 3 months starting from today
    for (let m = 0; m < 3; m++) {
        const d = new Date(today.getFullYear(), today.getMonth() + m, 1);
        const year = d.getFullYear();
        const month = d.getMonth();
        const monthName = d.toLocaleString('default', { month: 'long', year: 'numeric' });
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstDow = d.getDay(); // 0=Sun

        html += `<div class="avail-month"><div class="avail-month-title">${monthName}</div>`;
        html += '<div class="avail-grid">';
        ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(dh => {
            html += `<div class="avail-day-hdr">${dh}</div>`;
        });
        for (let i = 0; i < firstDow; i++) html += '<div></div>';
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const dt = new Date(year, month, day);
            const isPast = dt < today;
            const isBlocked = blocked.has(dateStr);
            let cls = 'avail-day';
            if (isPast)    cls += ' avail-past';
            else if (isBlocked) cls += ' avail-blocked-day';
            else           cls += ' avail-free-day';
            html += `<div class="${cls}">${day}</div>`;
        }
        html += '</div></div>'; // avail-grid + avail-month
    }

    html += '</div>'; // avail-cal-wrap
    container.innerHTML = html;
}

// LOGIC: Accommodations Page
function renderAccommodations() {
    const list = document.getElementById('accommodations-list');
    if (!list) return;

    list.innerHTML = rooms.map(room => `
        <div class="room-card-large">
            <div class="room-img-wrapper">
                <img src="${room.image}" alt="${room.name}">
            </div>
            <div class="room-content">
                <h2>${room.name}</h2>
                <p class="room-desc">${room.shortDescription}</p>
                <div class="room-meta">
                    <span>👥 ${room.capacity}</span>
                    <span>🏷️ ${room.price}</span>
                </div>
                <a href="room-details.html?id=${room.id}" class="btn btn-outline-dark">
                    View Details
                </a>
            </div>
        </div>
    `).join('');
}
