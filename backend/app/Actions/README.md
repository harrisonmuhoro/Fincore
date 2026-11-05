# Application actions

Place focused use-case orchestration here when a workflow spans domain objects or infrastructure. Actions define transaction boundaries and delegate business rules to the owning domain. Keep controllers limited to HTTP translation, validation and response mapping. Avoid generic service registries and unrelated cross-module actions.
