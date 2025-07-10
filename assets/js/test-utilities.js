/**
 * Test utilities for Claude code review testing
 * This file intentionally contains code quality issues for Claude to identify
 */

// Global variable - potential issue
var globalCounter = 0;

/**
 * User data handler with security and performance issues
 */
function handleUserData(userData) {
    // No input validation
    var userId = userData.id;
    var userName = userData.name;
    
    // Potential XSS vulnerability
    document.getElementById('user-display').innerHTML = userName;
    
    // Synchronous AJAX - blocks UI
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/api/user/' + userId, false);
    xhr.send();
    
    // No error handling
    var response = JSON.parse(xhr.responseText);
    return response;
}

/**
 * Price calculator with logic issues
 */
function calculatePrice(basePrice, taxRate, discountPercent) {
    // No type checking or validation
    var tax = basePrice * taxRate;
    var discount = basePrice * discountPercent / 100;
    
    // Potential floating point precision issues
    var finalPrice = basePrice + tax - discount;
    
    // No bounds checking
    if (finalPrice < 0) {
        finalPrice = 0; // This might not be the desired behavior
    }
    
    return finalPrice;
}

/**
 * Array processing with performance issues
 */
function processProductArray(products) {
    var result = [];
    
    // Inefficient nested loops
    for (var i = 0; i < products.length; i++) {
        for (var j = 0; j < products.length; j++) {
            if (products[i].category === products[j].category && i !== j) {
                // Creating objects in loop - memory inefficient
                result.push({
                    product1: products[i],
                    product2: products[j],
                    similarity: calculateSimilarity(products[i], products[j])
                });
            }
        }
    }
    
    return result;
}

/**
 * Similarity calculator - unclear logic
 */
function calculateSimilarity(product1, product2) {
    // Unclear algorithm
    var score = 0;
    
    if (product1.name == product2.name) score += 10;
    if (product1.price == product2.price) score += 5;
    if (product1.brand == product2.brand) score += 3;
    
    // Magic numbers without explanation
    return score > 7 ? 'high' : score > 3 ? 'medium' : 'low';
}

/**
 * Event handler with potential memory leaks
 */
function setupEventHandlers() {
    var buttons = document.querySelectorAll('.product-button');
    
    // Potential memory leak - no cleanup
    buttons.forEach(function(button) {
        button.addEventListener('click', function() {
            // Closure captures entire scope
            handleProductClick(button, globalCounter++);
        });
    });
}

/**
 * Product click handler with issues
 */
function handleProductClick(button, counter) {
    // No null checking
    var productId = button.dataset.productId;
    var productName = button.dataset.productName;
    
    // Modifying global state
    globalCounter = counter;
    
    // No error handling for API call
    fetch('/api/product/' + productId)
        .then(response => response.json())
        .then(data => {
            // Potential issue: assuming data structure
            updateProductDisplay(data.product.details);
        });
}

/**
 * Display update function with DOM manipulation issues
 */
function updateProductDisplay(productDetails) {
    // Direct DOM manipulation without checking if element exists
    document.getElementById('product-title').textContent = productDetails.name;
    document.getElementById('product-price').textContent = '$' + productDetails.price;
    
    // Potential XSS if productDetails.description contains HTML
    document.getElementById('product-description').innerHTML = productDetails.description;
    
    // No cleanup of previous event listeners
    document.getElementById('add-to-cart').onclick = function() {
        addToCart(productDetails.id);
    };
}

/**
 * Cart function with state management issues
 */
function addToCart(productId) {
    // Using localStorage without error handling
    var cart = JSON.parse(localStorage.getItem('cart'));
    
    // No validation of cart structure
    cart.items.push({
        id: productId,
        quantity: 1,
        addedAt: new Date()
    });
    
    // No error handling for localStorage
    localStorage.setItem('cart', JSON.stringify(cart));
    
    // Side effect without clear indication
    updateCartCounter();
}

/**
 * Utility function with unclear purpose
 */
function updateCartCounter() {
    // Accessing global DOM without checking
    var counter = document.querySelector('.cart-counter');
    var cart = JSON.parse(localStorage.getItem('cart'));
    
    // Potential null reference
    counter.textContent = cart.items.length;
}
