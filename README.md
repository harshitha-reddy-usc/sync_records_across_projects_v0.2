

# Sync Records Across Projects - External Module
<h2 style='color: #33B9FF;'>Configure Sync Records Across Projects</h2>
This module is based on Cross Project Piping Module 1.4.17. Cross Project Piping Module does piping based on matching single field whereas this module compares primary field for exact match and group of secondary fields for partial matches.
This module must be enabled on the DESTINATION project. Once enabled the configuration is done with in the
external module tab on the project.

![The module's 'Configure' button can be found under the 'Currently Enabled Modules' header in the project's External Modules page](/docs/readme_img_1.png)

<span style='font-weight: 600; text-decoration: underline;'>Project configurations that must be set up prior to field configurations.<span>
1. Go to your DESTINATION project.
2. In the Destination project, click on External Modules on the left-hand navigation bar. Then click on the
Configure button.
**3. Source Project:**
This field is the project the configuring user has access to.

![This picture shows project settings and values columns in the module's configuration view](/docs/readme_img_2.png)

**4. Unique Match Field:**
Select the unique field on the destination project that represents the record (first field of project). REDCap
uses record_id as the default value but can be different on every project.

![This picture shows the Unique Match Field setting](/docs/readme_img_3.png)

**5. Alternate Source Match Field:**
This is used if the unique match field on the destination project is different from the source project. For
example if Project A records are subject_id but Project B records are record_id.

![This picture shows the Alternate Source Match Field setting](/docs/readme_img_4.png)

**6. Secondary Unique Match Field:**
This is for specifying destination project’s instrument’s fields whose values should be checked for match in case the unique match field’s values is not a match. For example if Project A records are date_of_birth but Project B records are dob.

**7. Secondary Alternate Source Match Field:**
This is for specifying source project’s instrument’s fields whose values should be checked for match in case the unique match field’s values is not a match. For example if Project A records are subject_id but Project B records are record_id.

<span style='color: #ff0000;'>Note to add more secondary match fields select the + icon in the gray space to the right of Alternate Source Match Field:</span>

![This picture shows the Secondary Unique Match Field and Secondary Alternate Source Match Field setting](/docs/readme_img_10.png)

**8. Number of matches for a successful secondary match:**
This is for specifying the minimum number of secondary fields that has to be matched for a valid partial match case.

![This picture shows the fields to specify the minumum number of fields for successful secondary match ](/docs/readme_img_11.png)

**9. Field to populate for updating Cross Project Match status:**
 This is for specifying the field name that is added in the instrument, that stores the status of the match - whether it is exact(primary fields matched) or partial(minimum number of secondary field matched)

**10. Field to populate for updating Cross Project Matched ID:**
This is for specifying the field name that is added in the instrument, that stores the primary fields values of the source project’s records that was partially matched. 

**11. Field to populate for updating Cross Project Match - number of fields matched:**
This is for specifying the field name that is added in the instrument, that stores the number of successful partial matches corresponding to the ids that are present in the previous field.

**12. Field to populate for updating Cross Project Match - fields matched:**
This is for specifying the field name that is added in the instrument, that stores actual secondary match fields in the records that were successful match between source and a particular destination record.

![This picture shows the fields necessary to populate Cross Project Match Status, Cross Project Matched IDs, Number of Fields Matched, Fields Matched ](/docs/readme_img_12.png)

<span style='font-weight: 600; text-decoration: underline;'>Setting up your piped field.<span>
1. Select the destination field from the drop down list.

![This picture shows the Destinatinon Field setting](/docs/readme_img_5.png)

2. You will only need to enter a value in the Source field if the variable name on the destination field is
different from the source field.

![This picture shows the Source Field setting](/docs/readme_img_6.png)

<span style='color: #ff0000;'>Note to add more pipied fields select the + icon in the gray space to the right of Pipe Field:</span>

<span style='font-weight: 600; text-decoration: underline;'>Forms to allow piping<span>
Here you will select the instument piping will occur on. To add more then one instument select the + icon to the
right.

![This picture shows the active form setting](/docs/readme_img_7.png)

Once all configurations have been set make sure to select save at the bottom.
<span style='font-weight: 600; text-decoration: underline;'>Piping Mode<span>
There are two different ways to use piping:
4. Auto pipe on incomplete- This method will always load the piping screen and bring data into the
destination instrument every time the page is loaded unless the form is marked as complete.
5. Piping Button at top- This method will place an “Initiate Data Piping” button at the top of the
designated instument and only activate piping when selected.

<span style='color: #ff0000;'>Note: Auto piping will only run on 'Incomplete' status, and the piping button will only appear on instruments with an 'Incomplete' status. Once a record has moved from incomplete to any other status piping will not be available.
The status can always be reverted to incomplete to utilize this function.</span>

![This picture shows the Piping Mode setting (with Auto pipe selected)](/docs/readme_img_8.png)

![This picture shows the Piping Mode setting (with Piping Button selected)](/docs/readme_img_9.png)

<span style='color: #ff0000; font-size: 1.25rem;'>Please note that if Sync Records Across Projects is used there is a risk of overwriting data
in an instrument. Any record saved with data on it weather piped or not will save on that record.</span>

### Note: Field Embedding
The Sync Records Across Projects module may not work correctly when interacting with embedded fields.
