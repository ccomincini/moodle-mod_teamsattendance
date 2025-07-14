# Changelog

All notable changes to the Teams Attendance module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-07-14

### Added
- **Intelligent Name-Based Matching**: Automatic matching suggestions between Teams users and Moodle users based on name similarity
  - Supports multiple name formats: "LastName, FirstName", "FirstName LastName", etc.
  - Uses Levenshtein distance algorithm with 80% minimum similarity threshold
  - Handles edge cases with compound names and special characters
- **Visual Styling for Unassigned Records Page**:
  - Light green background for rows with suggested matches
  - Light orange background for rows without automatic matches  
  - Color legend to explain visual coding
  - Left border indicators for better visual distinction
  - Hover effects and visual feedback
- **Smart Record Sorting**: Suggested matches displayed first, followed by non-suggested records
- **Filtered User Lists**: Dropdown menus now exclude users already assigned to prevent duplicates
- **Bulk Operations**: Apply multiple suggested matches simultaneously with checkbox selection
- **Persistent Suggestion Tracking**: Applied suggestions are not shown again on page reload
- **Enhanced JavaScript Interactions**:
  - Confirmation dialogs for single and bulk operations
  - Visual feedback when suggestions are selected/deselected
  - Real-time button state management
- **Comprehensive Language Support**:
  - New English strings for all improved features
  - Complete Italian translations for all new functionality
  - User-friendly messages and notifications

### Changed
- **Improved manage_unassigned.php Interface**: Complete redesign with better UX and visual hierarchy
- **Enhanced User Assignment Workflow**: Streamlined process with intelligent suggestions
- **Optimized Database Queries**: More efficient retrieval of available users
- **Better Error Handling**: More informative messages for edge cases

### Technical Improvements
- **Algorithm Implementation**: Sophisticated name parsing and similarity calculation
- **CSS Styling**: Custom styles for improved visual distinction
- **Performance Optimization**: Reduced database queries and improved loading times
- **Code Organization**: Better separation of concerns and modular functions

### Security
- **Enhanced Validation**: Improved sesskey validation for all operations
- **Input Sanitization**: Better protection against malicious inputs
- **Capability Checks**: Proper permission verification for all actions

## [1.0.7] - 2025-06-23

### Fixed
- Various bug fixes and stability improvements
- Enhanced error handling for API connections

### Security
- Updated dependencies and security patches

## [1.0.6] - Previous Release

### Added
- Basic user assignment functionality
- Manual assignment capabilities
- Core attendance tracking features

---

## Version History Format

- **Added** for new features
- **Changed** for changes in existing functionality  
- **Deprecated** for soon-to-be removed features
- **Removed** for now removed features
- **Fixed** for any bug fixes
- **Security** in case of vulnerabilities
- **Technical Improvements** for code quality and performance enhancements
