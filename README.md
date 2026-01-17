# Blue – Beaver Builder Cloud Sync

Blue is a lightweight cloud sync tool for Beaver Builder assets. It was built to avoid repeating the same setup work across multiple projects and to make it easy to reuse proven components between client websites.

The focus of Blue is intentionally narrow: syncing **your own** Beaver Builder assets, without bundled templates, kits, or additional abstractions.

---

## FAQ

### What does Blue do?

Blue makes it easy to sync Beaver Builder templates, rows, columns, and modules across multiple websites. It does this by storing the underlying JSON representation of Beaver Builder assets in a database and exposing them through a REST API.

The WordPress plugin consumes this API and allows saved assets to be synced to any Beaver Builder–powered website. This significantly reduces repetitive setup work and makes it possible to reuse proven components across client projects.

### Doesn’t this already exist?

Yes. There are existing solutions that offer similar functionality as part of a broader feature set. However, those tools often include functionality I don’t need, such as large template libraries or additional extras.

Blue focuses solely on syncing my own Beaver Builder assets, without additional layers or bundled content. It is intentionally minimal and tailored to a specific workflow.

### Will this become a commercial product?

Possibly, but not at this stage. If you’re looking for a fully supported commercial solution today, Assistant.Pro already does a solid job.

### Can I help with development?

Yes. The WordPress plugin source code is available on GitHub. The API component is currently not open-source.

### Why is the API component currently not open-source?

The API is still evolving. Before open-sourcing it, I want to improve performance characteristics and complete a proper security review to ensure it’s safe to run in other environments.

~ Jasper