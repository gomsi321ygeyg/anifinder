// Comprehensive Search Suggestions System for Search Page
// Add this to your search page

document.addEventListener('DOMContentLoaded', function() {
    const searchBar = document.querySelector('#search-bar, .search-input, input[name="search"]');
    if (!searchBar) return;

    // Create suggestions container if it doesn't exist
    let suggestionsContainer = document.querySelector('#search-suggestions');
    if (!suggestionsContainer) {
        suggestionsContainer = document.createElement('div');
        suggestionsContainer.id = 'search-suggestions';
        suggestionsContainer.className = 'search-suggestions';
        searchBar.parentNode.appendChild(suggestionsContainer);
    }

    let allPosts = [];
    let currentSuggestions = [];
    let highlightedIndex = -1;
    let searchTimeout;

    // Extract all posts from current page and AJAX loaded content
    function extractAllPosts() {
        const posts = [];
        
        // Common selectors for post elements
        const postSelectors = [
            '.scroll-item',
            '.post-item', 
            '.video-item',
            '.content-item',
            '.grid-item',
            '[data-title]',
            '.post',
            '.video',
            '.item'
        ];

        postSelectors.forEach(selector => {
            document.querySelectorAll(selector).forEach(element => {
                const titleEl = element.querySelector('.item-title, .post-title, .title, h2, h3, .name') || element;
                const linkEl = element.querySelector('a') || element;
                
                let title = titleEl.textContent?.trim() || 
                           titleEl.getAttribute('data-title') || 
                           titleEl.getAttribute('title') || '';
                
                let url = linkEl.href || 
                         linkEl.getAttribute('data-url') || 
                         linkEl.getAttribute('href') || '';

                // Clean up title
                title = title.replace(/\s+/g, ' ').trim();
                
                if (title && url && title.length > 2 && !title.includes('undefined')) {
                    // Check for duplicates
                    const exists = posts.some(post => 
                        post.title === title || post.url === url
                    );
                    
                    if (!exists) {
                        posts.push({ title, url });
                    }
                }
            });
        });

        return posts;
    }

    // Monitor for AJAX content updates
    function setupAjaxMonitoring() {
        // Monitor DOM changes for AJAX loaded content
        const observer = new MutationObserver(function(mutations) {
            let shouldUpdate = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            // Check if new posts were added
                            const hasNewPosts = node.querySelector && (
                                node.querySelector('.scroll-item, .post-item, .video-item') ||
                                node.classList.contains('scroll-item') ||
                                node.classList.contains('post-item') ||
                                node.classList.contains('video-item')
                            );
                            
                            if (hasNewPosts) {
                                shouldUpdate = true;
                            }
                        }
                    });
                }
            });
            
            if (shouldUpdate) {
                setTimeout(updatePostsData, 500); // Small delay to ensure content is fully loaded
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Update posts data
    function updatePostsData() {
        const newPosts = extractAllPosts();
        if (newPosts.length > allPosts.length) {
            allPosts = newPosts;
            console.log(`Updated posts data: ${allPosts.length} total posts`);
        }
    }

    // Search function with intelligent ranking
    function searchPosts(query) {
        if (!query || query.length < 2) return [];

        const suggestions = [];
        const queryLower = query.toLowerCase();

        allPosts.forEach(post => {
            const titleLower = post.title.toLowerCase();
            const titleMatch = titleLower.indexOf(queryLower);

            if (titleMatch !== -1) {
                // Calculate relevance score
                let relevance = titleMatch;
                
                // Boost exact matches
                if (titleLower === queryLower) {
                    relevance = -100;
                }
                // Boost word boundary matches
                else if (titleMatch === 0 || titleLower[titleMatch - 1] === ' ') {
                    relevance = titleMatch - 10;
                }

                suggestions.push({
                    title: post.title,
                    url: post.url,
                    relevance: relevance,
                    match_type: 'title',
                    query: query
                });
            }
        });

        // Sort by relevance (lower score = higher relevance)
        suggestions.sort((a, b) => a.relevance - b.relevance);

        // Remove duplicates and limit results
        const uniqueSuggestions = [];
        const seenUrls = new Set();

        for (const suggestion of suggestions) {
            if (!seenUrls.has(suggestion.url) && uniqueSuggestions.length < 10) {
                seenUrls.add(suggestion.url);
                uniqueSuggestions.push(suggestion);
            }
        }

        return uniqueSuggestions;
    }

    // Highlight matching text
    function highlightMatch(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return text.replace(regex, '<span class="suggestion-match">$1</span>');
    }

    // Display suggestions
    function displaySuggestions(suggestions, query) {
        currentSuggestions = suggestions;
        highlightedIndex = -1;

        if (suggestions.length === 0) {
            hideSuggestions();
            return;
        }

        let html = '';
        suggestions.forEach((suggestion, index) => {
            html += `
                <div class="search-suggestion-item" data-index="${index}" data-url="${suggestion.url}">
                    <span class="suggestion-icon">🎬</span>
                    <span class="suggestion-text">${highlightMatch(suggestion.title, query)}</span>
                    <span class="suggestion-type">Post</span>
                </div>
            `;
        });

        suggestionsContainer.innerHTML = html;
        suggestionsContainer.classList.add('active');

        // Add click listeners
        suggestionsContainer.querySelectorAll('.search-suggestion-item').forEach(item => {
            item.addEventListener('click', function() {
                const url = this.dataset.url;
                if (url && url !== '#') {
                    window.open(url, '_blank', 'noopener');
                }
                hideSuggestions();
            });
        });
    }

    // Hide suggestions
    function hideSuggestions() {
        suggestionsContainer.classList.remove('active');
        suggestionsContainer.innerHTML = '';
        currentSuggestions = [];
        highlightedIndex = -1;
    }

    // Highlight suggestion
    function highlightSuggestion(index) {
        const items = suggestionsContainer.querySelectorAll('.search-suggestion-item');
        items.forEach(item => item.classList.remove('highlighted'));
        
        if (index >= 0 && index < items.length) {
            items[index].classList.add('highlighted');
            highlightedIndex = index;
        }
    }

    // Event listeners
    searchBar.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (query.length >= 2) {
                const suggestions = searchPosts(query);
                displaySuggestions(suggestions, query);
            } else {
                hideSuggestions();
            }
        }, 200);
    });

    searchBar.addEventListener('keydown', function(e) {
        const items = suggestionsContainer.querySelectorAll('.search-suggestion-item');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
            highlightSuggestion(highlightedIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightedIndex = Math.max(highlightedIndex - 1, -1);
            highlightSuggestion(highlightedIndex);
        } else if (e.key === 'Enter') {
            if (highlightedIndex >= 0 && items[highlightedIndex]) {
                e.preventDefault();
                const url = items[highlightedIndex].dataset.url;
                if (url && url !== '#') {
                    window.open(url, '_blank', 'noopener');
                }
                hideSuggestions();
            }
        } else if (e.key === 'Escape') {
            hideSuggestions();
        }
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchBar.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            hideSuggestions();
        }
    });

    // Initialize
    updatePostsData();
    setupAjaxMonitoring();

    // Also monitor scroll events for infinite scroll
    let scrollTimeout;
    window.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(updatePostsData, 1000);
    });

    console.log('Search suggestions system initialized with', allPosts.length, 'posts');
});