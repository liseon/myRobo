<?php
/**
* Exceptions
*/
class BTCeAPIException extends Exception {}
class BTCeAPIFailureException extends BTCeAPIException {}
class BTCeAPIInvalidJSONException extends BTCeAPIException {}
class BTCeAPIErrorException extends BTCeAPIException {}
class BTCeAPIInvalidParameterException extends BTCeAPIException {}