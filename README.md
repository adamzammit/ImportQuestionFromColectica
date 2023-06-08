# LimeSurvey Colectica Question Import Plugin
LimeSurvey plugin for importing questions from a Colectica repository

## Requirements
- LimeSurvey 3.X, 5.X, 6.X
- Colectica API URL, username and password

## Installation instructions
- Download the zip from the [releases](https://github.com/adamzammit/ImportQuestionFromColectica/releases) page and extract to your plugins folder.

## Configuration options

### Required
- **Colectica URL**: Url of the repository itself (not the API endpoint, this will be automatically appended)
- **Username**: Username of Colectica user who has API access
- **Password**: Password of Colectica user who has API access

## Usage
- Enable and configure the plugin
- A new button will appear when adding a new question called "Import from Colectica". Use this to browse/search the Colectica question repository and select and add questions to LimeSurvey
- Questions added via the plugin will now include a "Question Source" attribute which stores the URL of the question source from the Colectica repository for reference

### Example usage video
[limesurvey-colectica-plugin-2023-06-07_15.59.54.webm](https://github.com/adamzammit/ImportQuestionFromColectica/assets/1452303/6f2c6f11-36fe-43dc-824d-cfd54cc2a40d)

## Acknowledgements

- LimeSurvey: https://github.com/LimeSurvey/LimeSurvey
 
## Licence

GPLv3
