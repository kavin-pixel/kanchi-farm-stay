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
        price: "₹3,000 / night",
        numericPrice: 3000,
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
        capacity: "4 Adults, 1 Children",
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
        price: "₹2,000 / night",
        numericPrice: 2000,
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
        price: "₹2,000 / night",
        numericPrice: 2000,
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
            "assets/images/wooden-cottage-new-1.jpg",
            "assets/images/wooden-cottage-new-2.jpg",
            "assets/images/wooden-cottage-new-3.jpg",
            "assets/images/wooden-cottage-new-4.jpg",
            "assets/images/wooden-cottage-new-5.jpg",
            "assets/images/wooden-cottage-new-6.jpg",
            "assets/images/wooden-cottage-new-7.jpg"
        ],
        price: "₹2,500 / night",
        numericPrice: 2500,
        capacity: "3 Guests, 1 Bedroom, 2 Beds",
        size: "Contact for details",
        airbnbUrl: "https://airbnb.co.in/h/kanchifarmstay-woodencottage",
        bookingUrl: "index.html#contact"
    },
    {
        id: 'kanchi-farm-stay',
        name: "KanchiFarmStay (Group Booking)",
        shortDescription: "Exclusive booking of the entire farm stay for large groups and private events.",
        fullDescription: "Experience the ultimate private getaway by booking the entire Kanchi Farm Stay. Perfect for large family gatherings, corporate retreats, or special events. Enjoy exclusive access to all our villas, cottages, common areas, dining hall, and sprawling gardens without any other guests.",
        amenities: ["Exclusive Access", "All Bedrooms", "Dining Hall", "Full Kitchen", "Private Grounds", "Event Space", "Campfire", "Pet Friendly"],
        image: "assets/images/farm-hero.jpg",
        images: [
            "assets/images/farm-hero.jpg",
            "assets/images/farm-aerial.jpg",
            "assets/images/gallery-dining-hall-1.jpg",
            "assets/images/gallery-tent-camping.jpg",
            "assets/images/farm-exterior.jpg"
        ],
        price: "₹8,000 / night (up to 10 guests)",
        numericPrice: 8000,
        extraPersonPrice: 1000,
        baseGuests: 10,
        capacity: "Large Groups (10+ Guests)",
        size: "Entire 5-Acre Property",
        airbnbUrl: "#contact",
        bookingUrl: "index.html#contact"
    },
    {
        id: 'tent',
        name: "Tent",
        shortDescription: "Experience the outdoors in our comfortable tent.",
        fullDescription: "Sleep under the stars and wake up to the sound of nature in our spacious tent. It provides all the rustic charm of camping without sacrificing comfort. Perfect for adventurous couples or friends looking for a unique farm stay experience close to nature.",
        amenities: ["Comfortable Beds", "Shared Bathroom Access", "Campfire Access", "Star Gazing", "Pet Friendly", "Fan"],
        image: "assets/images/tent-accommodation-3.jpg",
        images: [
            "assets/images/tent-new-1.jpg",
            "assets/images/tent-new-2.jpg",
            "assets/images/tent-new-3.jpg",
            "assets/images/tent-new-4.jpg",
            "assets/images/tent-new-5.jpg",
            "assets/images/tent-new-6.jpg"
        ],
        price: "₹500 / night",
        numericPrice: 500,
        capacity: "2 Guests",
        size: "Cozy Tent Setup",
        airbnbUrl: "#contact",
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
    return pageName === page ? 'active' : '';
}

// LOGIC: Room Details Page
function loadRoomDetails() {
    const params = new URLSearchParams(window.location.search);
    const roomId = params.get('id');
    const room = rooms.find(r => r.id === roomId);

    if (room) {
        // Hero Image
        const heroImg = document.getElementById('room-hero-img');
        if (heroImg) heroImg.src = room.image;

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

        // Razorpay Date pickers and dynamic price
        const checkinInput = document.getElementById('room-checkin');
        const checkoutInput = document.getElementById('room-checkout');
        const totalPriceEl = document.getElementById('room-total-price');
        const razorpayBtn = document.getElementById('razorpay-btn');
        const guestCountContainer = document.getElementById('guest-count-container');
        const guestInput = document.getElementById('room-guests');

        if (checkinInput && checkoutInput && totalPriceEl && razorpayBtn) {
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);

            checkinInput.min = today.toISOString().split('T')[0];
            checkoutInput.min = tomorrow.toISOString().split('T')[0];

            let days = 1;
            let currentTotal = room.numericPrice;
            let numGuests = 2; // Default
            
            // Show guest input only for the group booking
            if (room.id === 'kanchi-farm-stay' && guestCountContainer && guestInput) {
                guestCountContainer.style.display = 'block';
                numGuests = parseInt(guestInput.value) || 10;
            }

            const updateTotalPrice = () => {
                const checkinDate = new Date(checkinInput.value);
                const checkoutDate = new Date(checkoutInput.value);

                if (checkinDate && checkoutDate && checkoutDate > checkinDate) {
                    const diffTime = Math.abs(checkoutDate - checkinDate);
                    days = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    let nightlyRate = room.numericPrice;
                    
                    // Add extra guests logic
                    if (room.id === 'kanchi-farm-stay' && guestInput) {
                        numGuests = Math.min(parseInt(guestInput.value) || 10, 25);
                        guestInput.value = numGuests;
                        if (numGuests > room.baseGuests) {
                            const extraGuests = numGuests - room.baseGuests;
                            nightlyRate += (extraGuests * room.extraPersonPrice);
                        }
                    }
                    
                    currentTotal = nightlyRate * days;
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
            if (guestInput) {
                guestInput.addEventListener('input', updateTotalPrice);
            }

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
                            days: days,
                            numGuests: numGuests
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
                        order_id: orderData.id, // The order ID from your backend
                        handler: function (response) {
                            alert(`Payment Successful!\nPayment ID: ${response.razorpay_payment_id}\nOrder ID: ${response.razorpay_order_id}\nRoom: ${room.name}\nCheck-in: ${checkinInput.value}\nCheck-out: ${checkoutInput.value}\nGuest: ${guestName}\nThank you for booking with Kanchi Farm Stay.`);
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


// LOGIC: Lightbox
function initLightbox() {
    // Create Lightbox Elements
    const lightbox = document.createElement('div');
    lightbox.id = 'lightbox';
    lightbox.className = 'lightbox';

    const img = document.createElement('img');
    img.id = 'lightbox-img';

    const closeBtn = document.createElement('div');
    closeBtn.className = 'lightbox-close';
    closeBtn.innerHTML = '&times;';

    lightbox.appendChild(img);
    lightbox.appendChild(closeBtn);
    document.body.appendChild(lightbox);

    // Event Listeners
    lightbox.addEventListener('click', (e) => {
        if (e.target !== img) {
            closeLightbox();
        }
    });

    closeBtn.addEventListener('click', closeLightbox);

    // Global Event Delegation for images
    document.addEventListener('click', (e) => {
        // Gallery Page Images or Room Details Images
        if (e.target.tagName === 'IMG' &&
            (e.target.closest('.gallery-grid') || e.target.closest('.room-gallery-grid') || e.target.closest('.gallery-item'))) {
            openLightbox(e.target.src);
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLightbox();
    });
}

function openLightbox(src) {
    const lightbox = document.getElementById('lightbox');
    const img = document.getElementById('lightbox-img');
    if (lightbox && img) {
        img.src = src;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    if (lightbox) {
        lightbox.classList.remove('active');
        document.body.style.overflow = ''; // Restore scrolling
    }
}

// INITIALIZATION
document.addEventListener('DOMContentLoaded', () => {
    renderNavbar();
    renderFooter();
    initLightbox();

    // Page specific logic
    const path = window.location.pathname;

    if (path.includes('room-details.html')) {
        loadRoomDetails();
    }

    if (path.includes('reviews.html')) {
        loadReviews();
    }

    if (path.includes('accommodations.html')) {
        renderAccommodations();
    }
});

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
