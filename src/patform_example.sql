CREATE TABLE `patform_example` (
  `json` json NOT NULL,
  `text` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'Default Text',
  `number` int NOT NULL,
  `checkbox` tinyint NOT NULL COMMENT '[boolean]',
  `password` int NOT NULL,
  `textarea` text NOT NULL,
  `date` bigint NOT NULL COMMENT '[unix]',
  `dropdown` enum('Option1','Option2','Option3','Option4') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'Option1',
  `twoOptionsEnum` enum('Red','Blue') NOT NULL DEFAULT 'Red'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
COMMIT;