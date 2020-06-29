# Prisoner Content Hub Backend Chart

## Prerequisites

**Helm 3**

Documentation for installing Helm can be found [here](https://helm.sh/docs/intro/quickstart/#install-helm)

## Dependencies

```
- Elasticsearch >= 7
- MariaDB >= 7
- AWS S3 Bucket
```

## Deployment

**Note:** The assumption is made that Helm is installed and your `kubectl` context is appropriately configured.

**Testing the release**

```
helm upgrade [Release Name] . \
--install --dry-run --debug\
--namespace [Kubernetes Namespace] \
--values values.[Environment].yaml \
--values secrets.yaml \
--set image.tag=[Image Tag]
```

The computed values and generated output will be displayed

**Perform the release**

Once tested and verified the release can be performed using the following

```
helm upgrade [Release Name] . \
--install --wait \
--namespace [Kubernetes Namespace] \
--values values.[Environment].yaml \
--values secrets.yaml \
--set image.tag=[Image Tag]
```

**Rolling back releases**

To list releases on the Namespace

```
helm --namespace [Kubernetes Namespace] list
```

View the revisions for a release

```
helm --namespace [Kubernetes Namespace] history [Release Name]
```

To rollback to a revision of a release

```
helm--namespace [Kubernetes Namespace] --wait rollback [Release Name] [Revision]
```
