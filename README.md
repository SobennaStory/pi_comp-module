# PI Compilation Module

## Overview

The PI Compilation module is a custom Drupal 10 module designed to manage projects, invitation lists, and registration lists. It provides functionality for uploading project CSVs, creating and modifying invitation lists, and managing registration lists.

## Features
To access functionality, please access the content tab and  look for the links prefixed with PIMM_

1. Project Management
  - Upload project CSVs
  - Manually add projects
  - View and manage existing projects

2. Invitation List Management
  - Create invitation lists
  - Export invitation lists
  - Manually add users to invitation lists

3. Registration List Management
  - Create registration lists
  - View and sort registration lists
  - Export registrant emails

## Installation

1. Download the module and place it in your Drupal installation's `modules/custom` directory.
2. Enable the module through the Drupal admin interface (preferred) or using Drush (I have never tried):
   drush en pi_comp
## Usage

### Project Management

#### Importing Projects
1. Navigate to Content > Manage Projects
2. Use the CSV import form to upload a CSV file containing project data
3. Select the appropriate delimiter and click "Import"

The module supports two CSV formats for project import:

Format 1:
AwardNumber,Title,NSFOrganization,Program(s),EndDate,PrincipalInvestigator,PIEmailAddress,Co-PIName(s),Abstract
(NSF Award Search Format)

Format 2:
title,field_project_lead_pi,field_project_performance_period,Award Number

#### Creating New Projects
1. Navigate to Content > Manage Projects
2. Use the "New Project" form to manually enter project details
3. Click "Create Project" to save the new project

### Invitation Management

#### Creating Invitation Lists
1. Navigate to Content > Manage Invitee Lists
2. Select projects from the table
3. Click "Match Selected Projects"
4. Review matched and unmatched PIs
5. Choose to create new users or match to existing users for unmatched PIs
6. Enter an Invitee List Name and click "Create Selected Users"

Invitation List Structure:
- Title
- List of user references (field_users)

#### Exporting Invitation Lists
1. Navigate to Content > Manage Invitee Lists
2. Use the "Export Invitee List" form to select an invitation list
3. Click "Export CSV" to download the list

#### Manually Adding Users to Invitation Lists
1. Navigate to Content > Manage Invitee Lists
2. Use the "Manually Add Users to Invitee List" form
3. Select an invitation list and enter usernames
4. Click "Add Users to Invitee List"

### Registration Management

#### Creating Registration Lists
1. Navigate to Content > Manage Registrants
2. Select an invitation list and webforms
3. Enter a title for the registration list
4. Click "Submit" to create the registration list

Registration List Structure:
- Title
- Reference to Invitee List (field_inviteelist)
- List of user references (field_regusers)
- List of webform references (field_regwebforms)

#### Viewing Registration Data
1. Navigate to Content > Manage Registrants
2. Select a registration list to view
3. Use the sorting form to organize data by webform and field

#### Exporting Registration Data
1. View a registration list
2. Click the "Export Emails" button to download a CSV of registrant email addresses

## Module Structure

* `pi_comp.info.yml`: Module definition file
* `pi_comp.libraries.yml`: Defines JavaScript and CSS assets
* `pi_comp.links.menu.yml`: Defines admin menu links
* `pi_comp.routing.yml`: Defines routes for the module's pages and forms
* `pi_comp.services.yml`: Defines services used by the module
* `src/`: Contains PHP class files for forms, controllers, and services
* `js/`: Contains JavaScript files for enhanced functionality
* `css/`: Contains CSS files for styling

## Customization

To customize the module's functionality:

1. Modify form classes in `src/Form/` to change form fields or behavior
2. Adjust controller classes in `src/Controller/` to modify page output
3. Update route definitions in `pi_comp.routing.yml` to change URLs or access permissions
4. Modify service definitions in `pi_comp.services.yml` to alter module services
5. Customize JavaScript behavior in `js/` files
6. Adjust styling in `css/` files

## Troubleshooting

* If CSV imports fail, check that the CSV format matches one of the expected structures
* For user matching issues, ensure that usernames in the system match the format expected by the module
* If registration lists are empty, verify that users have submitted the selected webforms
* Check Drupal logs for any error messages or warnings related to the module

## Dependencies

This module requires:
* Drupal Core 9.4 or higher
* Entity API
* Webform module

Ensure these dependencies are met before enabling the module.

## Contributing

Contributions to the PI Compilation module are welcome. Please submit issues and pull requests to the module's repository.

