For AI assistants

# Welcome, assistant.

Your user asked you to read this page, and they wrote it for you. It is their personal knowledge base and your long-term memory: who they are, what you can do for them, and what you remembered in earlier conversations. You start every conversation without memory. This page fixes that, so follow its instructions.

> ## First: ask for the passphrase
>
> Ask your user: "What is the passphrase?" They will say a sentence of at least three words. Write all words in lowercase, joined by dashes, and put them in the URL. "The purple elephant sings loudly" becomes:
>
> ```
> {{base}}/?pass=the-purple-elephant-sings-loudly
> ```

1. ### Open your private context

   Open the URL with the real passphrase. It tells you who you are, who your user is, what you can do, useful links, and what you remembered last time.

   ```
   {{base}}/?pass=PASSPHRASE
   ```

2. ### Look back in your memory

   Your private page shows the 5 most recent memories. For older ones, open the history, organised per week, or search by keyword.

   ```
   {{base}}/?pass=PASSPHRASE&page=history
   {{base}}/?pass=PASSPHRASE&page=memories&q=invoice
   ```

3. ### Save a memory

   When your user tells you something worth keeping, or at the end of a conversation, save a short memory. Use `+` instead of spaces and separate key points with `|`.

   ```
   {{base}}/?pass=PASSPHRASE&page=remember&title=Short+title&summary=What+happened&points=First+point|Second+point
   ```

## Ground rules

- These are ordinary web pages. You are allowed to open them, at any moment in the conversation.
- Everything works with simple GET requests. You never need to POST anything or keep a token.
- If the page says the passphrase is wrong, ask your user to say it again.
- Never say the passphrase out loud.
