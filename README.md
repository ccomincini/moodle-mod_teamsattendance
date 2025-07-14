# Teams Meeting Attendance Plugin for Moodle

[![Moodle Plugin](https://img.shields.io/badge/moodle-plugin-blue.svg)](https://moodle.org)
[![Version](https://img.shields.io/badge/version-v1.1.0-green.svg)](https://github.com/ccomincini/moodle-mod_teamsattendance/releases)
[![License](https://img.shields.io/badge/license-GPL%20v3-blue.svg)](http://www.gnu.org/copyleft/gpl.html)
[![Moodle](https://img.shields.io/badge/moodle-4.0%2B-orange.svg)](https://moodle.org)

A comprehensive Moodle activity module for tracking and managing student attendance in Microsoft Teams meetings with intelligent user matching and advanced reporting capabilities.

## 🚀 Key Features

### 🎯 **Core Functionality**
- **Automatic Attendance Tracking**: Seamlessly tracks student participation in Teams meetings
- **Meeting Integration**: Direct integration with Microsoft Teams through Graph API
- **Percentage Calculation**: Automatic calculation of attendance percentages based on meeting duration
- **Completion Tracking**: Full support for Moodle's activity completion system
- **Role-based Tracking**: Supports different participant roles (Attendee, Presenter, Organizer)

### 🔍 **Intelligent User Matching** *(New in v1.1.0)*
- **Smart Name Matching**: Automatic suggestions for matching Teams participants to Moodle users
- **Multiple Format Support**: Handles various name formats ("LastName, FirstName", "FirstName LastName", etc.)
- **Similarity Algorithm**: Uses Levenshtein distance with 80% minimum confidence threshold
- **Bulk Operations**: Apply multiple matching suggestions simultaneously

### 🎨 **Enhanced User Interface** *(New in v1.1.0)*
- **Visual Color Coding**: 
  - 🟢 Green rows for suggested matches
  - 🟠 Orange rows for unmatched records
- **Smart Sorting**: Suggested matches displayed first for better workflow
- **Interactive Elements**: Real-time feedback and confirmation dialogs
- **Responsive Design**: Optimized for desktop and mobile devices

### 📊 **Advanced Management**
- **Filtered User Lists**: Prevents duplicate assignments by excluding already assigned users
- **Persistent Tracking**: Applied suggestions are remembered to avoid re-prompting
- **Manual Override**: Teachers can manually assign users when automatic matching fails
- **Detailed Reports**: Comprehensive attendance reports with export capabilities

### 🌐 **Multilingual Support**
- **English**: Complete localization
- **Italian**: Full translation support
- **Extensible**: Easy to add additional languages

## 📋 Requirements

| Component | Version | Notes |
|-----------|---------|-------|
| **Moodle** | 4.0+ | Core LMS platform |
| **PHP** | 7.4+ | Server-side scripting |
| **Database** | MySQL 5.7+ / PostgreSQL 10+ | Data storage |
| **Microsoft Graph API** | v1.0 | Teams integration |
| **Browser** | Modern browsers | JavaScript enabled |

### 🔗 Dependencies
- `auth_oidc` plugin for Microsoft authentication
- Valid Microsoft 365 tenant with Teams access
- Appropriate API permissions for attendance reports

## 🛠️ Installation

### Method 1: Manual Installation

1. **Download the Plugin**
   ```bash
   # Download from GitHub releases
   wget https://github.com/ccomincini/moodle-mod_teamsattendance/archive/v1.1.0.zip
   ```

2. **Extract to Moodle Directory**
   ```bash
   # Extract to mod directory
   unzip v1.1.0.zip -d /path/to/moodle/mod/
   mv moodle-mod_teamsattendance-1.1.0 teamsattendance
   ```

3. **Set Permissions**
   ```bash
   # Set appropriate permissions
   chown -R www-data:www-data /path/to/moodle/mod/teamsattendance
   chmod -R 755 /path/to/moodle/mod/teamsattendance
   ```

4. **Complete Installation**
   - Login as administrator
   - Navigate to Site Administration → Notifications
   - Follow the installation wizard

### Method 2: Git Installation

```bash
# Clone repository
cd /path/to/moodle/mod/
git clone https://github.com/ccomincini/moodle-mod_teamsattendance.git teamsattendance

# Set permissions
chown -R www-data:www-data teamsattendance/
chmod -R 755 teamsattendance/
```

### Method 3: Moodle Plugin Directory *(Coming Soon)*
The plugin will be available through the official Moodle Plugin Directory for one-click installation.

## ⚙️ Configuration

### 🔧 Global Settings

Navigate to **Site Administration → Plugins → Activity modules → Teams Meeting Attendance**

#### Microsoft Teams API Configuration
```
🔑 Tenant ID: [Your Azure AD Tenant ID]
🌐 API Endpoint: https://graph.microsoft.com
📋 API Version: v1.0
🔐 Authentication: OIDC Plugin Integration
```

#### Default Settings
```
📊 Default Completion Threshold: 80%
⏱️ Default Meeting Duration: 60 minutes
📈 Report Generation: Automatic
🔄 Data Sync Interval: 15 minutes
```

### 🎛️ Activity Configuration

When creating a Teams Meeting Attendance activity:

#### Basic Settings
- **Activity Name**: Descriptive name for the meeting
- **Description**: Meeting purpose and objectives
- **Meeting URL**: Teams meeting invitation link
- **Organizer Email**: Email of the meeting organizer

#### Timing Configuration
- **Expected Duration**: Planned meeting length
- **Start Time**: Optional filter for attendance reports
- **End Time**: Optional filter for attendance reports

#### Completion Settings
- **Required Attendance %**: Minimum attendance for completion
- **Enable Completion**: Toggle activity completion tracking

## 📚 Usage Guide

### 👨‍🏫 For Teachers

#### Creating a Meeting Activity
1. **Add Activity**
   - Go to your course
   - Turn editing on
   - Add Activity → Teams Meeting Attendance

2. **Configure Settings**
   ```
   📝 Name: "Weekly Lecture - Introduction to Data Science"
   📧 Organizer: teacher@university.edu
   🔗 Meeting URL: [Teams invitation link]
   ⏱️ Duration: 90 minutes
   📊 Required Attendance: 75%
   ```

3. **Save and Launch**

#### Managing Attendance
1. **Fetch Attendance Data**
   - Click "Fetch Attendance" after the meeting
   - System automatically processes Teams data
   - View preliminary results

2. **Handle Unassigned Records** *(Enhanced in v1.1.0)*
   - Click "Manage Unassigned Records"
   - Review intelligent matching suggestions (🟢 green rows)
   - Handle unmatched records (🟠 orange rows)
   - Apply bulk suggestions or assign manually

3. **Generate Reports**
   - Export attendance data to CSV/Excel
   - View detailed statistics
   - Track completion status

#### Advanced Features
- **Manual Assignments**: Override automatic matching when needed
- **Attendance Adjustments**: Modify attendance percentages manually
- **Completion Tracking**: Monitor student progress toward completion

### 👨‍🎓 For Students

#### Joining Meetings
1. **Access Activity**: Click on the Teams Meeting Attendance activity
2. **Join Meeting**: Use the provided Teams link
3. **Automatic Tracking**: Attendance is recorded automatically
4. **View Status**: Check your attendance percentage after the meeting

#### Viewing Progress
- **Attendance History**: See all your meeting participations
- **Completion Status**: Track progress toward activity completion
- **Statistics**: View your attendance patterns and trends

### 👨‍💼 For Administrators

#### System Monitoring
- **Plugin Health**: Monitor API connections and data sync
- **Performance Metrics**: Track system usage and response times
- **User Management**: Oversee permissions and access rights

#### Maintenance Tasks
- **Data Cleanup**: Regular maintenance of old attendance records
- **API Monitoring**: Ensure Microsoft Graph API connectivity
- **Backup Verification**: Confirm attendance data is properly backed up

## 🎨 Visual Interface Guide

### Color Coding System *(New in v1.1.0)*

| Color | Meaning | Action Required |
|-------|---------|-----------------|
| 🟢 **Light Green** | Automatic match suggested | Review and apply suggestion |
| 🟠 **Light Orange** | No automatic match found | Manual assignment needed |
| ⚪ **White** | Already assigned | No action needed |

### User Interface Elements

#### Manage Unassigned Records Page
```
🎯 Suggestions Found: 5 automatic matching suggestions based on names
[Apply Selected Suggestions] ← Bulk action button

┌─ Color Legend ─────────────────────────────────────┐
│ 🟢 Suggested Matches    🟠 No Automatic Matches   │
└────────────────────────────────────────────────────┘

Teams User          | Time  | % | Suggested Match    | Actions
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🟢 Smith, John       | 45min | 90| ✓ John Smith       | [Assign ▼]
🟢 Doe, Jane        | 38min | 76| ✓ Jane Doe         | [Assign ▼]
🟠 Unknown User     | 22min | 44| No suggestion      | [Select ▼]
```

## 🔧 Troubleshooting

### Common Issues

#### Authentication Problems
```bash
❌ Issue: "Tenant ID not configured"
✅ Solution: Configure Microsoft API settings in plugin configuration

❌ Issue: "OIDC authentication failed"
✅ Solution: Verify auth_oidc plugin is installed and configured
```

#### Data Sync Issues
```bash
❌ Issue: "No attendance data found"
✅ Solution: Ensure meeting organizer email is correct
✅ Solution: Check API permissions for attendance reports

❌ Issue: "Partial attendance data"
✅ Solution: Verify meeting timeframe settings
✅ Solution: Check if meeting has ended before fetching data
```

#### User Matching Problems
```bash
❌ Issue: "No automatic suggestions"
✅ Solution: Verify user names in Teams match Moodle profile names
✅ Solution: Check if users are already assigned to other records

❌ Issue: "Incorrect matching suggestions"
✅ Solution: Use manual assignment override
✅ Solution: Adjust user profile names for better matching
```

### Debug Mode

Enable debug mode for detailed logging:
```php
// Add to config.php for debugging
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;
```

### Log Analysis

Check Moodle logs for plugin-specific errors:
- Navigate to Site Administration → Reports → Logs
- Filter by "Teams Attendance" component
- Look for error-level messages

## 🔌 API Reference

### Core Functions

#### Attendance Data Retrieval
```php
/**
 * Fetch attendance data from Microsoft Teams
 * @param int $sessionid Teams meeting session ID
 * @param string $organizer_email Meeting organizer email
 * @return array Attendance records
 */
function fetch_teams_attendance($sessionid, $organizer_email);
```

#### User Matching
```php
/**
 * Get name-based matching suggestions
 * @param array $unassigned_records Teams attendance records
 * @param array $available_users Moodle course users
 * @return array Matching suggestions
 */
function get_name_based_suggestions($unassigned_records, $available_users);
```

#### Bulk Operations
```php
/**
 * Apply multiple user assignments
 * @param array $suggestions Array of recordid => userid mappings
 * @return int Number of successful assignments
 */
function apply_bulk_assignments($suggestions);
```

### Database Schema

#### Main Tables
```sql
-- Core attendance sessions
CREATE TABLE mdl_teamsattendance (
    id BIGINT PRIMARY KEY,
    course BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    intro LONGTEXT,
    meeting_url TEXT,
    organizer_email VARCHAR(255),
    expected_duration INT,
    required_attendance INT DEFAULT 80,
    timemodified BIGINT
);

-- Individual attendance records
CREATE TABLE mdl_teamsattendance_data (
    id BIGINT PRIMARY KEY,
    sessionid BIGINT NOT NULL,
    userid BIGINT,
    teams_user_id VARCHAR(255),
    attendance_duration INT,
    actual_attendance DECIMAL(5,2),
    manually_assigned TINYINT DEFAULT 0,
    timerecorded BIGINT
);
```

### Events

#### Custom Events Triggered
- `attendance_updated`: When attendance data is refreshed
- `user_assigned`: When a user is manually assigned
- `bulk_assignment_completed`: When bulk operations complete

## 🧪 Testing

### Unit Testing
```bash
# Run PHPUnit tests
vendor/bin/phpunit mod/teamsattendance/tests/

# Run specific test class
vendor/bin/phpunit mod/teamsattendance/tests/attendance_test.php
```

### Behat Testing
```bash
# Run Behat features
vendor/bin/behat --config behatdata/behat.yml --tags @mod_teamsattendance
```

### Manual Testing Checklist

#### Basic Functionality
- [ ] Create Teams Meeting Attendance activity
- [ ] Configure meeting settings
- [ ] Fetch attendance data from Teams
- [ ] View attendance reports

#### Enhanced Features (v1.1.0)
- [ ] Verify color coding in unassigned records page
- [ ] Test automatic name matching suggestions
- [ ] Apply bulk suggestions
- [ ] Confirm manual assignment functionality
- [ ] Verify persistent suggestion tracking

#### Edge Cases
- [ ] Handle meetings with no participants
- [ ] Test with invalid meeting URLs
- [ ] Verify behavior with network interruptions
- [ ] Test with special characters in names

## 📈 Performance Considerations

### Optimization Tips

#### Database Performance
- **Indexing**: Ensure proper indexes on frequently queried fields
- **Query Optimization**: Use efficient SQL queries for large datasets
- **Caching**: Implement caching for repeated API calls

#### API Efficiency
- **Rate Limiting**: Respect Microsoft Graph API rate limits
- **Batch Operations**: Use bulk operations where possible
- **Error Handling**: Implement robust retry mechanisms

#### User Experience
- **Pagination**: Implement pagination for large attendance lists
- **Async Operations**: Use background processing for time-consuming tasks
- **Progress Indicators**: Show progress for long-running operations

## 🔒 Security

### Data Protection
- **GDPR Compliance**: Attendance data handling follows GDPR requirements
- **Data Encryption**: Sensitive data encrypted in transit and at rest
- **Access Control**: Role-based permissions for all operations

### API Security
- **OAuth 2.0**: Secure authentication with Microsoft services
- **Token Management**: Automatic token refresh and revocation
- **Permission Scoping**: Minimal required permissions requested

### Input Validation
- **SQL Injection Protection**: All queries use prepared statements
- **XSS Prevention**: All user inputs properly sanitized
- **CSRF Protection**: Session keys validated for all form submissions

## 🚀 Roadmap

### Upcoming Features

#### Version 1.2.0 (Planned)
- [ ] **Advanced Analytics**: Detailed participation analytics and trends
- [ ] **Integration Enhancements**: Support for additional meeting platforms
- [ ] **Mobile App**: Dedicated mobile application for attendance tracking
- [ ] **AI Insights**: Machine learning for attendance pattern analysis

#### Version 1.3.0 (Future)
- [ ] **Real-time Tracking**: Live attendance monitoring during meetings
- [ ] **Custom Reports**: User-defined report templates
- [ ] **API Extensions**: RESTful API for third-party integrations
- [ ] **Advanced Notifications**: Automated attendance alerts and reminders

### Community Contributions
We welcome contributions from the community! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## 🤝 Support

### Getting Help

#### Documentation
- **Plugin Documentation**: Comprehensive guides and tutorials
- **Moodle Docs**: Official Moodle development documentation
- **API Reference**: Microsoft Graph API documentation

#### Community Support
- **GitHub Issues**: Report bugs and request features
- **GitHub Discussions**: Community Q&A and discussions
- **Moodle Forums**: General Moodle plugin discussions

#### Professional Support
For enterprise support and custom development:
- **Email**: carlo@comincini.it
- **Website**: [Invisible Farm](https://invisiblefarm.it)

## 📄 License

This plugin is licensed under the [GNU General Public License v3.0](LICENSE).

```
Copyright (C) 2025 Carlo Comincini

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🏆 Credits

### Development Team
- **Carlo Comincini** - Lead Developer - [GitHub](https://github.com/ccomincini)
- **Invisible Farm Srl** - Development Company

### Acknowledgments
- **Moodle Community** - For the excellent platform and development guidelines
- **Microsoft** - For the Teams and Graph API platforms
- **Contributors** - All community members who help improve this plugin

### Third-Party Libraries
- **Microsoft Graph SDK** - Teams integration
- **Moodle Core APIs** - Platform integration
- **jQuery** - Enhanced user interactions

---

## 📊 Repository Statistics

![GitHub stars](https://img.shields.io/github/stars/ccomincini/moodle-mod_teamsattendance?style=social)
![GitHub forks](https://img.shields.io/github/forks/ccomincini/moodle-mod_teamsattendance?style=social)
![GitHub issues](https://img.shields.io/github/issues/ccomincini/moodle-mod_teamsattendance)
![GitHub pull requests](https://img.shields.io/github/issues-pr/ccomincini/moodle-mod_teamsattendance)

**Made with ❤️ for the Moodle community**
