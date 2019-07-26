# LeadVertex API client plugin component

`\Leadvertex\Plugin\ApiClient\ApiClient` - component, which accept API endpoint URL and API token, and 
allow make queries to leadvertex.com [GraphQL](https://graphql.org/) API. This component use under the hood 
[softonic/graphql-client](https://packagist.org/packages/softonic/graphql-client) GraphQL client library 

## Usage

For example, in your plugin app you can create internationalization class like this
```php
<?php
$client = new \Leadvertex\Plugin\ApiClient\ApiClient('https://cluster.leadvertex.com/companies/1/CRM', 'token here');

/** @var \Softonic\GraphQL\Response $response */
$response = $client->query('query {name, company}');

print_r($response->getData());
print_r($response->getErrors());