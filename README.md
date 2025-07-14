# Teams Meeting Attendance Plugin for Moodle

## Overview
The Teams Meeting Attendance plugin for Moodle allows instructors to track and manage attendance for Microsoft Teams meetings. It provides a comprehensive solution for monitoring student participation in virtual meetings and automatically calculates attendance percentages based on meeting duration.

## Features
- **Meeting Integration**: Seamlessly integrates with Microsoft Teams meetings
- **Automatic Attendance Tracking**: Records when students join and leave meetings
- **Attendance Percentage Calculation**: Automatically calculates attendance percentages based on meeting duration
- **Completion Tracking**: Supports Moodle's completion tracking system
- **Detailed Reports**: Provides detailed attendance reports for each meeting
- **Role-based Tracking**: Supports different roles in meetings (Attendee, Presenter, etc.)
- **Backup and Restore**: Full support for Moodle's backup and restore functionality

## Requirements
- Moodle 4.0 or later
- Microsoft Teams account with appropriate permissions
- Microsoft Graph API access

## Installation
1. Download the plugin files
2. Extract the files to the `mod/teamsattendance` directory of your Moodle installation
3. Visit the Site Administration page to complete the installation
4. Configure the Microsoft Teams integration settings

## Configuration
### Global Settings
1. Navigate to Site Administration > Plugins > Activity modules > Teams Meeting Attendance
2. Configure the following settings:
   - Microsoft Teams API credentials
   - Default attendance requirements
   - Meeting duration settings
   - Report generation preferences

### Activity Settings
When creating a new Teams Meeting Attendance activity, you can configure:
- Meeting URL
- Organizer email
- Expected meeting duration
- Required attendance percentage
- Completion criteria
- Meeting status

## Usage
### Creating a Meeting
1. Add a new Teams Meeting Attendance activity to your course
2. Configure the meeting settings:
   - Enter the Teams meeting URL
   - Set the organizer's email
   - Define the expected duration
   - Set the required attendance percentage
3. Save the activity

### Tracking Attendance
1. Start your Teams meeting at the scheduled time
2. The plugin will automatically:
   - Record when participants join and leave
   - Calculate attendance duration
   - Track meeting roles
   - Generate attendance reports

### Viewing Reports
1. Access the Teams Meeting Attendance activity
2. View the attendance dashboard showing:
   - Overall attendance statistics
   - Individual student attendance records
   - Detailed meeting reports
   - Completion status

## Backup and Restore
The plugin supports Moodle's backup and restore functionality:
- All meeting data is backed up
- Attendance records are preserved
- Reports are included in the backup
- File attachments are maintained

## Database Structure
The plugin uses three main tables:
1. `teamsattendance`: Stores meeting session data
2. `teamsattendance_data`: Stores individual attendance records
3. `teamsattendance_reports`: Stores detailed meeting reports

## License
This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html)

## Credits
Developed by Invisiblefarm Srl 