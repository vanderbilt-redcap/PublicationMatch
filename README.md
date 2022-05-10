
**PublicationMatch REDCap External Module Documentation**

This REDCap external module was created to pull publication match data from the SRI API into REDCap once a day.

**Configuration**

Admins can configure the module by adding SRI API Source names to the repeatable list. You can do this by clicking the "Configure" button on the external modules "Manage" page. Clicking the "+" button will allow you to enter more than one SRI API Source name. A list of available source name values can be found here:
https://starbrite.app.vumc.org/s/sri/doc

Note, that you will need to be on the VUMC VPN in order to access the "Swagger UI" where the SRI API documentation and examples are hosted.

**Fetching Data from the SRI API**

The module will automatically request data from the API every day by way of it's scheduled cron job. This happens at 1:01AM server time each morning. The module will attempt to get Publication Match data for each source listed in the module's configuration settings. If it fails, it will log the reason why to the project's Logging page. If it succeeds, a new record will be created with today's returned results. The [data] field contains a JSON encoded array. After decoding, the array will contain values for each source name.

For instance, the [data] field may contain a value like this after importing data:
	{"biovu":"{"message":"No publication matches found for given parameters.","data":null}","vicc":"{"message":"No publication matches found for given parameters.","data":null}","coeus":"{"message":"No publication matches found for given parameters.","data":null}","aou":"{"message":"No publication matches found for given parameters.","data":null}"}
	
Here, "biovu", "vicc", "coeus", and "aou" are all source name values that are keys in the data array. The values associated with these keys are the results returned from the SRI API for that source. These result strings can be JSON decoded to provide "message", "data", or if applicable, "error" information.
