# config
file `config/packages/synergy_maker.yaml` : 
```yaml
synergy_maker:
    bundleName: '@efrogg/synergy'
    snippetPrefix: 'budget'
    editFormPrefix: 'Bg'
```

# customize
## form
field form configuration can be defined in entity, through a `#[SynergyFormField]` annotation
```php
    #[SynergyFormField(ignore: true)]
    private ?Budget $budget = null;
```
parameters : 
* `ignore` : boolean, if true, the field will not be generated in the form


# command

```bash
# generate only typescript entity
php bin/console synergy:generate MyEntity --entity
# generate only vue form
php bin/console synergy:generate MyEntity --form
# generate only form and entity
php bin/console synergy:generate MyEntity --full

# select all entities
php bin/console synergy:generate --all --full
```
