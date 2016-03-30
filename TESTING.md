Manual testing
==============

 1. Add users 'Peter', 'Bob' and 'Mary' to group 'searchtest'
 2. Users 'John' and 'Tailor' must NOT be members of 'searchtest'
 3. Log in as 'Peter'
 4. Create path /testing/search/
 5. upload the document.(docx|odt|pdf|txt) to /testing/search/
 6. wait until cron.php has been executed via cron (not ajax)
 7. User 'Peter' should find the four documents when searching for 'Lorem' or 'Lore*'
 8. User 'Bob'   should NOT find any of the four documents when searching for 'Lorem' or 'Lore*'
 9. User 'John'  should NOT find any of the four documents when searching for 'Lorem' or 'Lore*'
10. Share '/testing' to group 'searchtest'
11. wait until cron.php has been executed via cron (not ajax)
12. User 'Peter' should find the four documents when searching for 'Lorem' or 'Lore*'
13. User 'Bob'   should find the four documents when searching for 'Lorem' or 'Lore*'
14. User 'John'  should NOT find any of the four documents when searching for 'Lorem' or 'Lore*'
15. Share '/testing' to user 'Tailor'
16. wait until cron.php has been executed via cron (not ajax)
17. User 'Peter'  should find the four documents when searching for 'Lorem' or 'Lore*'
18. User 'Bob'    should find the four documents when searching for 'Lorem' or 'Lore*'
19. User 'Tailor' should find the four documents when searching for 'Lorem' or 'Lore*'
20. User 'John'   should NOT find any of the four documents when searching for 'Lorem' or 'Lore*'
21. Unshare '/testing' for group 'searchtest'
22. wait until cron.php has been executed via cron (not ajax)
23. User 'Peter'  should find the four documents when searching for 'Lorem' or 'Lore*'
24. User 'Bob'    should NOT find any of the four documents when searching for 'Lorem' or 'Lore*'
25. User 'Tailor' should find the four documents when searching for 'Lorem' or 'Lore*'
26. User 'John'   should NOT find any of the four documents when searching for 'Lorem' or 'Lore*'
27. Unshare '/testing' for user 'Tailor'
28. wait until cron.php has been executed via cron (not ajax)
29. User 'Peter'  should find the four documents when searching for 'Lorem' or 'Lore*'
30. User 'Bob'    should NOT find any of the four documents when searching for 'Lorem' or 'Lore*'
31. User 'Tailor' should NOT find any of the four documents when searching for 'Lorem' or 'Lore*'
32. User 'John'   should NOT find any of the four documents when searching for 'Lorem' or 'Lore*'
