/**
 * Frontend JavaScript for Blaze Slideshow Block
 */

class BlazeSlideshow {
	constructor(element) {
		this.slideshow = element;
		this.track = element.querySelector('.blaze-slideshow-track');
		this.slides = Array.from(this.track.children);
		this.currentIndex = 0;
		this.isTransitioning = false;
		this.autoplayTimer = null;
		
		// Get settings from data attributes
		this.settings = {
			slidesToShowDesktop: parseInt(element.dataset.slidesDesktop) || 3,
			slidesToShowTablet: parseInt(element.dataset.slidesTablet) || 2,
			slidesToShowMobile: parseInt(element.dataset.slidesMobile) || 1,
			enableArrows: element.dataset.enableArrows === 'true',
			enableDots: element.dataset.enableDots === 'true',
			enableAutoplay: element.dataset.enableAutoplay === 'true',
			autoplaySpeed: parseInt(element.dataset.autoplaySpeed) || 3000,
			infinite: element.dataset.infinite === 'true',
			speed: parseInt(element.dataset.speed) || 500,
			slidesToScroll: parseInt(element.dataset.slidesToScroll) || 1,
			arrowColor: element.dataset.arrowColor || '#333333',
			dotColor: element.dataset.dotColor || '#cccccc',
			dotActiveColor: element.dataset.dotActiveColor || '#333333',
		};

		this.init();
	}

	init() {
		if (this.slides.length === 0) return;

		this.setupResponsive();
		this.setupNavigation();
		this.setupAutoplay();
		this.setupEventListeners();
		this.updateSlideshow();
	}

	setupResponsive() {
		// Set CSS custom properties for responsive behavior
		this.slideshow.style.setProperty('--slides-desktop', this.settings.slidesToShowDesktop);
		this.slideshow.style.setProperty('--slides-tablet', this.settings.slidesToShowTablet);
		this.slideshow.style.setProperty('--slides-mobile', this.settings.slidesToShowMobile);
		this.slideshow.style.setProperty('--transition-speed', `${this.settings.speed}ms`);
		this.slideshow.style.setProperty('--dot-color', this.settings.dotColor);
		this.slideshow.style.setProperty('--dot-active-color', this.settings.dotActiveColor);
	}

	setupNavigation() {
		// Create arrow navigation
		if (this.settings.enableArrows) {
			this.createArrows();
		}

		// Create dot navigation
		if (this.settings.enableDots) {
			this.createDots();
		}
	}

	createArrows() {
		const prevArrow = document.createElement('button');
		prevArrow.className = 'blaze-slideshow-arrow prev';
		prevArrow.innerHTML = `
			<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		`;
		prevArrow.style.color = this.settings.arrowColor;
		prevArrow.addEventListener('click', () => this.prevSlide());

		const nextArrow = document.createElement('button');
		nextArrow.className = 'blaze-slideshow-arrow next';
		nextArrow.innerHTML = `
			<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		`;
		nextArrow.style.color = this.settings.arrowColor;
		nextArrow.addEventListener('click', () => this.nextSlide());

		this.slideshow.appendChild(prevArrow);
		this.slideshow.appendChild(nextArrow);

		this.prevArrow = prevArrow;
		this.nextArrow = nextArrow;
	}

	createDots() {
		const dotsContainer = document.createElement('div');
		dotsContainer.className = 'blaze-slideshow-dots';

		const totalDots = Math.ceil(this.slides.length / this.getCurrentSlidesToShow());

		for (let i = 0; i < totalDots; i++) {
			const dot = document.createElement('button');
			dot.className = 'dot';
			dot.addEventListener('click', () => this.goToSlide(i * this.settings.slidesToScroll));
			dotsContainer.appendChild(dot);
		}

		this.slideshow.appendChild(dotsContainer);
		this.dotsContainer = dotsContainer;
		this.dots = Array.from(dotsContainer.children);
	}

	setupAutoplay() {
		if (this.settings.enableAutoplay) {
			this.startAutoplay();
		}
	}

	setupEventListeners() {
		// Pause autoplay on hover
		if (this.settings.enableAutoplay) {
			this.slideshow.addEventListener('mouseenter', () => this.pauseAutoplay());
			this.slideshow.addEventListener('mouseleave', () => this.startAutoplay());
		}

		// Handle window resize
		window.addEventListener('resize', () => this.handleResize());

		// Handle touch events for mobile swipe
		this.setupTouchEvents();
	}

	setupTouchEvents() {
		let startX = 0;
		let startY = 0;
		let endX = 0;
		let endY = 0;

		this.slideshow.addEventListener('touchstart', (e) => {
			startX = e.touches[0].clientX;
			startY = e.touches[0].clientY;
		});

		this.slideshow.addEventListener('touchend', (e) => {
			endX = e.changedTouches[0].clientX;
			endY = e.changedTouches[0].clientY;

			const deltaX = startX - endX;
			const deltaY = startY - endY;

			// Only trigger swipe if horizontal movement is greater than vertical
			if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
				if (deltaX > 0) {
					this.nextSlide();
				} else {
					this.prevSlide();
				}
			}
		});
	}

	getCurrentSlidesToShow() {
		const width = window.innerWidth;
		if (width >= 1024) {
			return this.settings.slidesToShowDesktop;
		} else if (width >= 768) {
			return this.settings.slidesToShowTablet;
		} else {
			return this.settings.slidesToShowMobile;
		}
	}

	updateSlideshow() {
		const slidesToShow = this.getCurrentSlidesToShow();
		const slideWidth = 100 / slidesToShow;
		const translateX = -(this.currentIndex * slideWidth);

		this.track.style.transform = `translateX(${translateX}%)`;

		// Update dots
		if (this.dots) {
			this.dots.forEach((dot, index) => {
				const isActive = index === Math.floor(this.currentIndex / this.settings.slidesToScroll);
				dot.classList.toggle('active', isActive);
			});
		}

		// Update arrows
		if (this.prevArrow && this.nextArrow && !this.settings.infinite) {
			this.prevArrow.disabled = this.currentIndex === 0;
			this.nextArrow.disabled = this.currentIndex >= this.slides.length - slidesToShow;
		}
	}

	nextSlide() {
		if (this.isTransitioning) return;

		const slidesToShow = this.getCurrentSlidesToShow();
		const maxIndex = this.settings.infinite ? this.slides.length : this.slides.length - slidesToShow;

		if (this.currentIndex < maxIndex - this.settings.slidesToScroll) {
			this.currentIndex += this.settings.slidesToScroll;
		} else if (this.settings.infinite) {
			this.currentIndex = 0;
		}

		this.updateSlideshow();
	}

	prevSlide() {
		if (this.isTransitioning) return;

		if (this.currentIndex > 0) {
			this.currentIndex -= this.settings.slidesToScroll;
		} else if (this.settings.infinite) {
			const slidesToShow = this.getCurrentSlidesToShow();
			this.currentIndex = this.slides.length - slidesToShow;
		}

		this.updateSlideshow();
	}

	goToSlide(index) {
		if (this.isTransitioning) return;

		this.currentIndex = Math.max(0, Math.min(index, this.slides.length - this.getCurrentSlidesToShow()));
		this.updateSlideshow();
	}

	startAutoplay() {
		if (!this.settings.enableAutoplay) return;

		this.pauseAutoplay();
		this.autoplayTimer = setInterval(() => {
			this.nextSlide();
		}, this.settings.autoplaySpeed);
	}

	pauseAutoplay() {
		if (this.autoplayTimer) {
			clearInterval(this.autoplayTimer);
			this.autoplayTimer = null;
		}
	}

	handleResize() {
		// Debounce resize events
		clearTimeout(this.resizeTimer);
		this.resizeTimer = setTimeout(() => {
			this.updateSlideshow();
		}, 250);
	}

	destroy() {
		this.pauseAutoplay();
		window.removeEventListener('resize', this.handleResize);
	}
}

// Initialize all slideshows when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	const slideshows = document.querySelectorAll('.wp-block-blaze-commerce-blaze-slideshow');
	slideshows.forEach(slideshow => {
		new BlazeSlideshow(slideshow);
	});
});

// Export for potential external use
window.BlazeSlideshow = BlazeSlideshow;
