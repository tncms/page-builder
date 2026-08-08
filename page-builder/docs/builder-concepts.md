# Page Builder — Sections, Elements & Patterns

*A first-time guide to the Live Builder's model. Phase 26.3.18.*

The Builder has three kinds of building blocks. Understanding the difference is the
key to using it without frustration.

---

## What is a Section?

A **Section** is a top-level band of your page — a hero, a call-to-action, a row of
logos. Sections stack vertically to form the page's major structure.

- **Sections live only at the page level.** You can reorder them, but you cannot put
  a Section *inside* another element.
- Add them from the **Sections** panel.
- Examples: `Hero`, `Text Section`, `Image Section`, `Columns`, `CTA`, `FAQ`,
  `Testimonial`, `Logo Strip`.

Every Section card carries a **`ROOT ONLY`** badge to remind you.

---

## What is an Element?

An **Element** is a piece of content that lives *inside* a layout — inside a Container
or a Column.

- **Elements go inside Containers and Columns**, never directly at the page level.
- Add them from the **Elements** panel.
- Content elements: `Text`, `Heading`, `Image`, `Button`, `Icon`, `Quote`, `Logo`,
  `Accordion Item` — each shows a **`COLUMN READY`** badge.
- Layout elements: `Container` (**`LAYOUT`**) holds Columns; `Column`
  (**`CHILD LAYOUT`**) holds content elements.

To add an Element, first **select a Container or Column**, then click the Element (or
drag it onto the canvas). If nothing suitable is selected, the Element cards are
dimmed and the panel shows: *"Select a Container or Column before adding an Element."*

---

## What is a Pattern?

A **Pattern** is a ready-made layout that already combines Sections and Elements — a
complete, styled block you can drop in and edit. Patterns are the fastest way to
build; Sections and Elements are for fine-grained control.

Find them under the **Patterns** tab.

---

## Typical hierarchy

```
Page
└── Section            (ROOT ONLY — e.g. Hero, Columns)
    └── Container      (LAYOUT — holds columns)
        └── Column     (CHILD LAYOUT — holds content)
            ├── Heading   (Element)
            ├── Text      (Element)
            └── Image     (Element)
```

Read it top-down: **Sections** frame the page, **Columns** divide space, **Elements**
fill it.

---

## Common mistakes

**"I dragged an Image Section into a Column and it jumped to the bottom of the page."**
An *Image Section* is a Section — it can only sit at page level, so it snaps there. If
you wanted an image *inside* the column, use the **Image** element instead. When a
layout is selected, the Sections panel offers a shortcut:
**"Insert Image element instead."**

**"Text Section vs Text — which one?"**
`Text Section` is a whole page band. `Text` is a paragraph you place inside a column.
Same idea for `Image Section` vs `Image`.

**"The Element cards are greyed out."**
Nothing that can hold an Element is selected. Select a Container or Column first.

**"Why did dropping a Section onto a Column not nest it?"**
By design — Sections are page-level. A short, non-blocking note explains this; your
editing is never interrupted, and the Section is simply placed at page level.

---

## Accessibility

- Every component card exposes its guidance as a tooltip **and** an accessible label
  (screen readers announce the same hint sighted users see — no hover-only info).
- Icons are always paired with a text label.
- The guidance toast is a polite ARIA live region and never traps focus.
- Capability badges (`ROOT ONLY`, `COLUMN READY`, `LAYOUT`, `CHILD LAYOUT`) are text.
