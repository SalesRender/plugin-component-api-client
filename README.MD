# SalesRender API client plugin component

`\SalesRender\Plugin\Components\ApiClient\ApiClient` - component, which accept API endpoint URL and API token, and 
allow make queries to salesrender.com [GraphQL](https://graphql.org/) API. This component use under the hood 
[softonic/graphql-client](https://packagist.org/packages/softonic/graphql-client) GraphQL client library 

## Usage

For example, in your plugin app you can create internationalization class like this
```php
<?php
$client = new \SalesRender\Plugin\Components\ApiClient\ApiClient('https://cluster.salesrender.com/companies/1/CRM', 'token here');

/** @var \Softonic\GraphQL\Response $response */
$response = $client->query('query {name, company}');

print_r($response->getData());
print_r($response->getErrors());