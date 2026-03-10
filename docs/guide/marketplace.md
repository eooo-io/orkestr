# Marketplace

The marketplace is a self-hosted platform for publishing, discovering, and installing community skills. It runs within your Agentis Studio instance and stores everything in the local database.

## Browsing

Navigate to **Marketplace** from the sidebar. The page shows:

- A **category sidebar** for filtering
- A **search bar** with FULLTEXT search across names and descriptions
- **Sort options** -- most popular, newest, highest rated
- **Pagination** for large result sets

Each skill card displays the name, description, author, download count, and average rating.

## Publishing a Skill

To publish a skill from one of your projects to the marketplace:

1. Open the skill in the Skill Editor
2. The publish flow creates a marketplace entry with:
   - Skill name, description, and body
   - Category and tags
   - Author information
   - A snapshot of the current version

Published skills are visible to anyone with access to your Agentis Studio instance.

### API

```
POST /api/marketplace/publish
```

Request body includes the skill ID, category, and optional description override.

## Installing a Marketplace Skill

Click **Install** on a marketplace skill card:

1. Select the target project
2. Confirm the install

The skill is imported into your project just like a [library import](./library) -- a new skill is created, the file is written to disk, tags are synced, and slug collisions are handled with numeric suffixes.

Each install increments the skill's download counter.

```
POST /api/marketplace/{id}/install
```

## Voting

Rate marketplace skills with upvote or downvote buttons on the skill card. Votes affect the skill's average rating and influence sort order in "highest rated" view.

```
POST /api/marketplace/{id}/vote
```

Request body: `{ "vote": 1 }` (upvote) or `{ "vote": -1 }` (downvote).

## Viewing Details

Click a marketplace skill to see its full details:

```
GET /api/marketplace/{id}
```

The detail view shows the complete skill body, all metadata, download count, rating, and publication date.

::: info
The marketplace is self-hosted. If you run multiple Agentis Studio instances, each has its own independent marketplace. There is no central registry.
:::
