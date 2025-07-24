# Cache System Technical Documentation

## Overview

Il sistema di cache in mod_teamsattendance utilizza sessionStorage per ottimizzare la navigazione nell'interfaccia di gestione dei record non assegnati. Questo documento fornisce un'analisi tecnica completa del sistema.

## Current Implementation

### Cache Architecture

```javascript
// Cache Key Format
var cacheKey = 'page_' + page + '_' + JSON.stringify(filters) + '_' + actualPageSize;

// Example Keys
'page_0_{}_50'                                    // All records, page 0, 50 per page
'page_1_{"suggestion_type":"name_based"}_100'    // Name suggestions, page 1, 100 per page
'page_0_{"suggestion_type":"email_based"}_all'   // Email suggestions, page 0, all records
```

### Cache Usage Flow

```javascript
loadPage: function(page, forceRefresh) {
    // 1. Generate cache key
    var cacheKey = 'page_' + page + '_' + filtersHash + '_' + actualPageSize;
    
    // 2. Check cache (unless force refresh)
    if (!forceRefresh && sessionStorage.getItem(cacheKey)) {
        var cachedData = JSON.parse(sessionStorage.getItem(cacheKey));
        this.renderPage(cachedData);
        return; // Skip AJAX
    }
    
    // 3. Make AJAX request
    $.ajax({
        success: function(response) {
            // 4. Store in cache
            sessionStorage.setItem(cacheKey, JSON.stringify(response.data));
            self.renderPage(response.data);
        }
    });
}
```

### Cache Invalidation

Cache viene invalidata automaticamente in questi scenari:

```javascript
// 1. Filter or page size changes
applyCurrentSettings: function() {
    var filterChanged = (newFilter !== this.currentFilter);
    var pageSizeChanged = (newPageSizeNum !== this.currentPageSize);
    
    if (filterChanged || pageSizeChanged) {
        sessionStorage.clear(); // Clear ALL cache
    }
    
    this.loadPage(0, true); // Force refresh
}

// 2. After assignments
applySingleSuggestion: function() {
    // ... assignment logic ...
    sessionStorage.clear(); // Clear cache after assignment
}

// 3. After bulk operations
performBulkAssignment: function() {
    // ... bulk assignment logic ...
    sessionStorage.clear(); // Clear cache after bulk operations
}
```

## Performance Analysis

### Cache Benefits

**Scenarios where cache is useful:**
- User navigates between pages of the same filter view
- Typical workflow: apply filter → browse pages 1,2,3 → go back to page 1

**Performance gains:**
- Cache hit: ~1-5ms vs AJAX call: ~200-500ms
- Significant improvement for page navigation within same filter

### Cache Limitations

**Scenarios where cache is NOT useful:**
- Changing filters (cache invalidated)
- Changing page size (cache invalidated)
- After assignments (cache invalidated)
- Initial page load (no cache available)

**Real usage patterns:**
- Users frequently change filters (invalidates cache)
- Users frequently make assignments (invalidates cache)
- Page navigation within same filter is less common

## Bug History

### July 2025 Cache Bug

**Problem:**
Filters stopped working with page sizes 20 and 50, but worked with 100 and "all".

**Root Cause:**
```javascript
// Old problematic logic
if (sessionStorage.getItem(cacheKey)) {
    // Used cached data even when filters changed
    return cachedData; // Wrong! Should have made new AJAX call
}
```

**Symptoms:**
- Change filter from "all" to "name_suggestions" with page size 50
- System used cached data from "all" filter instead of making new AJAX call
- No records shown because cached data didn't match new filter

**Fix Applied:**
```javascript
// Added cache invalidation
if (filterChanged || pageSizeChanged) {
    sessionStorage.clear(); // Clear cache on filter changes
}
this.loadPage(0, true); // Force refresh bypasses cache
```

**Commits:** 8c8f3ba, 732c1f9

## Code Complexity Analysis

### With Cache (Current Implementation)

```javascript
// Cache key generation
var filtersHash = JSON.stringify(filters);
var cacheKey = 'page_' + page + '_' + filtersHash + '_' + actualPageSize;

// Cache check logic
if (!forceRefresh && sessionStorage.getItem(cacheKey)) {
    var cachedData = JSON.parse(sessionStorage.getItem(cacheKey));
    this.renderPage(cachedData);
    this.isLoading = false;
    $('#loading-indicator').hide();
    return;
}

// Cache storage
sessionStorage.setItem(cacheKey, JSON.stringify(response.data));

// Cache invalidation in multiple places
if (filterChanged || pageSizeChanged) {
    sessionStorage.clear();
}

// Force refresh parameter throughout codebase
this.loadPage(0, true); // true = force refresh
```

### Without Cache (Proposed Simplification)

```javascript
// Always make AJAX call - no cache logic needed
$.ajax({
    url: window.location.href,
    method: 'GET',
    data: {
        ajax: 1,
        action: 'load_page',
        page: page,
        per_page: actualPageSize,
        filters: JSON.stringify(filters),
        sesskey: this.sesskey
    },
    success: function(response) {
        if (response.success) {
            self.renderPage(response.data);
        }
    },
    complete: function() {
        self.isLoading = false;
        $('#loading-indicator').hide();
    }
});
```

## Recommendation Analysis

### Factors Supporting Cache Removal

1. **Low Effective Utility**
   - Cache only benefits page navigation within same filter
   - This represents <20% of typical user interactions
   
2. **High Code Complexity**
   - Cache key generation logic
   - Invalidation logic in multiple functions
   - Force refresh parameter threading
   - ~50+ lines of cache-specific code

3. **Bug Susceptibility**
   - Recent bug was directly caused by cache logic
   - Complex invalidation rules increase bug potential
   - Cache invalidation is error-prone by nature

4. **Marginal Performance Benefit**
   - AJAX calls are fast (~200-500ms)
   - Users don't navigate between pages frequently enough to justify complexity
   - Loading indicators provide good UX for AJAX delays

### Factors Supporting Cache Retention

1. **Existing Functionality**
   - Cache works correctly after bug fix
   - No immediate need to change working code

2. **Potential User Experience**
   - Instant page navigation when cache hits
   - Reduced server load for repeated page views

## Future Refactoring Guidelines

If cache removal is chosen in the future:

### Files to Modify
- `amd/src/unassigned_manager.js`
- `amd/build/unassigned_manager.min.js`

### Changes Required

1. **Remove from loadPage():**
   ```javascript
   // Remove these lines:
   var filtersHash = JSON.stringify(filters);
   var cacheKey = 'page_' + page + '_' + filtersHash + '_' + actualPageSize;
   
   if (!forceRefresh && sessionStorage.getItem(cacheKey)) {
       // ... cache logic ...
   }
   
   sessionStorage.setItem(cacheKey, JSON.stringify(response.data));
   ```

2. **Remove forceRefresh parameter:**
   ```javascript
   // Change from:
   loadPage: function(page, forceRefresh)
   // To:
   loadPage: function(page)
   
   // Update all calls:
   this.loadPage(0, true) → this.loadPage(0)
   this.loadPage(page) // Already correct
   ```

3. **Remove cache invalidation:**
   ```javascript
   // Remove from applyCurrentSettings():
   sessionStorage.clear();
   
   // Remove from applySingleSuggestion():
   sessionStorage.clear();
   
   // Remove from performBulkAssignment():
   sessionStorage.clear();
   ```

### Testing Requirements
- Verify all filter combinations work correctly
- Test page navigation without cache
- Confirm no performance degradation in typical usage
- Validate AJAX error handling without cache fallback

## Conclusion

The cache system adds significant complexity for marginal benefit. The recent bug demonstrates the fragility of cache invalidation logic. While the current implementation works correctly after the fix, future consideration should be given to removing the cache entirely to simplify the codebase and reduce bug potential.

**Estimated effort for cache removal:** 2-3 hours
**Risk level:** Low (simplification reduces risk)
**Performance impact:** Minimal (200-500ms AJAX calls are acceptable)

---

**Document Version:** 1.0  
**Last Updated:** July 24, 2025  
**Author:** Technical Analysis based on mod_teamsattendance codebase
