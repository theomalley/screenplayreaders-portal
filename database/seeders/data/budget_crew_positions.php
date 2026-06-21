<?php

// All 97 crew positions with rate tier data
// Sourced from step-02-budget-calculations.js SCALE_* constants (lines 25-966)
// Last verified: 2026-02-11
//
// Format: each entry = [line_item_id, slug, name, department, guild, sort_order, tiers]
// tiers: array of [tier_code, rate_type, rate_value, add_pub_fee]
// rate_type: 'flat', 'weekly', 'hourly', 'min_wage'

return [
    // ── WRITING ──
    ['210', 'writer', 'Writer', 'writing', 'WGA', 1, [
        [200, 'flat', 22726.00, false],
        [201, 'flat', 45452.00, false],
        [202, 'flat', 61064.00, true],
        [203, 'flat', 91354.00, true],
        [299, 'flat', 171485.00, true],
    ]],

    // ── DIRECTING ──
    ['410', 'director', 'Director', 'directing', 'DGA_DIR', 10, [
        [301, 'min_wage', 0, false],
        [302, 'min_wage', 0, false],
        [304, 'weekly', 18449.00, false],
        [305, 'weekly', 18449.00, false],
        [306, 'weekly', 17568.00, false],
        [399, 'weekly', 24599.00, false],
    ]],

    // ── PRODUCTION (DGA_UPM) ──
    ['910', 'upm', 'UPM', 'production', 'DGA_UPM', 20, [
        [400, 'min_wage', 0, false],
        [401, 'weekly', 2317.00, false],
        [402, 'weekly', 3536.00, false],
        [403, 'weekly', 4413.00, false],
        [404, 'weekly', 8149.00, false],
        [405, 'weekly', 9314.00, false],
        [406, 'weekly', 10478.00, false],
        [499, 'weekly', 11642.00, false],
    ]],
    ['912', '1stad', '1st AD', 'production', 'DGA_UPM', 21, [
        [400, 'min_wage', 0, false],
        [401, 'weekly', 2203.00, false],
        [402, 'weekly', 3363.00, false],
        [403, 'weekly', 4206.00, false],
        [404, 'weekly', 6537.00, false],
        [405, 'weekly', 8688.00, false],
        [406, 'weekly', 9774.00, false],
        [499, 'weekly', 10860.00, false],
    ]],
    ['914', '2ndad', '2nd AD', 'production', 'DGA_UPM', 22, [
        [400, 'min_wage', 0, false],
        [401, 'weekly', 1476.00, false],
        [402, 'weekly', 2262.00, false],
        [403, 'weekly', 2834.00, false],
        [404, 'weekly', 5243.00, false],
        [405, 'weekly', 5992.00, false],
        [406, 'weekly', 6741.00, false],
        [499, 'weekly', 7490.00, false],
    ]],
    ['916', '2nd2ndad', '2nd 2nd AD', 'production', 'DGA_UPM', 23, [
        [400, 'min_wage', 0, false],
        [401, 'weekly', 1007.00, false],
        [402, 'weekly', 1286.00, false],
        [403, 'weekly', 1543.00, false],
        [404, 'weekly', 2524.00, false],
        [405, 'weekly', 2885.00, false],
        [406, 'weekly', 3245.00, false],
        [499, 'weekly', 5905.00, false],
    ]],
    ['918', 'addl2ndad', 'Additional 2nd 2nd AD', 'production', 'DGA_UPM', 24, [
        [400, 'min_wage', 0, false],
        [401, 'weekly', 1007.00, false],
        [402, 'weekly', 1286.00, false],
        [403, 'weekly', 1543.00, false],
        [404, 'weekly', 2524.00, false],
        [405, 'weekly', 2885.00, false],
        [406, 'weekly', 3245.00, false],
        [499, 'weekly', 5905.00, false],
    ]],

    // ── PRODUCTION (IATSE) ──
    ['920', 'scriptsuper', 'Script Supervisor', 'production', 'IATSE', 25, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 33.26, false],
        [502, 'hourly', 34.87, false],
        [503, 'hourly', 44.09, false],
        [504, 'hourly', 46.39, false],
        [599, 'weekly', 3437.70, false],
    ]],
    ['922', 'prodcoord', 'Production Coordinator', 'production', 'IATSE', 26, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 33.26, false],
        [502, 'hourly', 34.87, false],
        [503, 'hourly', 43.75, false],
        [504, 'hourly', 46.04, false],
        [599, 'hourly', 46.04, false],
    ]],
    ['924', 'asstprodcoord', 'Asst Production Coordinator', 'production', 'IATSE', 27, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 30.06, false],
        [502, 'hourly', 31.66, false],
        [503, 'hourly', 39.55, false],
        [504, 'hourly', 41.65, false],
        [599, 'hourly', 41.65, false],
    ]],
    ['926', 'prodacct', 'Production Accountant', 'production', 'IATSE', 28, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 33.26, false],
        [502, 'hourly', 34.87, false],
        [503, 'hourly', 43.75, false],
        [504, 'hourly', 46.04, false],
        [599, 'hourly', 46.04, false],
    ]],
    ['928', 'asstprodacct', 'Asst Production Accountant', 'production', 'IATSE', 29, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 30.06, false],
        [502, 'hourly', 31.66, false],
        [503, 'hourly', 39.55, false],
        [504, 'hourly', 41.65, false],
        [599, 'hourly', 41.65, false],
    ]],
    ['930', 'teacher', 'Teacher', 'production', 'IATSE', 30, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 33.26, false],
        [502, 'min_wage', 0, false],
        [503, 'min_wage', 0, false],
        [504, 'min_wage', 0, false],
        [599, 'weekly', 3241.65, false],
    ]],

    // ── CAMERA ──
    ['1010', 'dp', 'Director of Photography', 'camera', 'IATSE', 40, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 53.72, false],
        [502, 'hourly', 64.46, false],
        [503, 'hourly', 75.21, false],
        [504, 'hourly', 85.95, false],
        [599, 'hourly', 107.44, false],
    ]],
    ['1012', '1stac', '1st AC', 'camera', 'IATSE', 41, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 33.26, false],
        [502, 'hourly', 34.87, false],
        [503, 'hourly', 54.75, false],
        [504, 'hourly', 57.64, false],
        [599, 'hourly', 63.36, false],
    ]],
    ['1014', '2ndac', '2nd AC', 'camera', 'IATSE', 42, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 30.06, false],
        [502, 'hourly', 31.66, false],
        [503, 'hourly', 41.96, false],
        [504, 'hourly', 44.16, false],
        [599, 'hourly', 58.57, false],
    ]],
    ['1016', 'cameraop', 'Camera Operator', 'camera', 'IATSE', 43, [
        [500, 'min_wage', 0, false],
        [501, 'min_wage', 0, false],
        [502, 'hourly', 39.08, false],
        [503, 'hourly', 63.11, false],
        [504, 'hourly', 66.44, false],
        [599, 'hourly', 71.66, false],
    ]],
    ['1018', 'steadicamop', 'Steadicam Operator', 'camera', 'IATSE', 44, [
        [500, 'min_wage', 0, false],
        [501, 'min_wage', 0, false],
        [502, 'hourly', 39.08, false],
        [503, 'hourly', 63.11, false],
        [504, 'hourly', 66.44, false],
        [599, 'hourly', 71.66, false],
    ]],
    ['1020', 'dit', 'DIT', 'camera', 'IATSE', 45, [
        [500, 'min_wage', 0, false],
        [501, 'min_wage', 0, false],
        [502, 'hourly', 39.08, false],
        [503, 'hourly', 63.11, false],
        [504, 'hourly', 66.44, false],
        [599, 'hourly', 82.39, false],
    ]],
    ['1022', 'photographer', 'Still Photographer', 'camera', 'IATSE', 46, [
        [500, 'min_wage', 0, false],
        [501, 'min_wage', 0, false],
        [502, 'hourly', 39.08, false],
        [503, 'hourly', 63.11, false],
        [504, 'hourly', 66.44, false],
        [599, 'hourly', 72.69, false],
    ]],

    // ── SECOND UNIT ──
    ['1110', '2ndunitdir', '2nd Unit Director', 'second_unit', 'DGA_UPM', 50, [
        [403, 'weekly', 7500.00, false],
        [404, 'weekly', 18449.00, false],
        [405, 'weekly', 18449.00, false],
        [406, 'weekly', 17568.00, false],
        [499, 'weekly', 24599.00, false],
    ]],
    ['1112', '2ndunitdp', '2nd Unit DP', 'second_unit', 'IATSE', 51, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 53.72, false],
        [502, 'hourly', 64.46, false],
        [503, 'hourly', 75.21, false],
        [504, 'hourly', 85.95, false],
        [599, 'hourly', 107.44, false],
    ]],
    ['1114', '2ndunit1stac', '2nd Unit 1st AC', 'second_unit', 'IATSE', 52, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 33.26, false],
        [502, 'hourly', 34.87, false],
        [503, 'hourly', 54.75, false],
        [504, 'hourly', 57.64, false],
        [599, 'hourly', 63.36, false],
    ]],
    ['1116', '2ndunit2ndac', '2nd Unit 2nd AC', 'second_unit', 'IATSE', 53, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 30.06, false],
        [502, 'hourly', 31.66, false],
        [503, 'hourly', 41.96, false],
        [504, 'hourly', 44.16, false],
        [599, 'hourly', 58.57, false],
    ]],
    ['1118', '2ndunitcameraop', '2nd Unit Camera Op', 'second_unit', 'IATSE', 54, [
        [500, 'min_wage', 0, false],
        [501, 'min_wage', 0, false],
        [502, 'hourly', 39.08, false],
        [503, 'hourly', 63.11, false],
        [504, 'hourly', 66.44, false],
        [599, 'hourly', 71.66, false],
    ]],

    // ── PRODUCTION SOUND ──
    ['1210', 'prodmixer', 'Production Mixer', 'production_sound', 'IATSE', 60, [
        [500, 'min_wage', 0, false],
        [501, 'min_wage', 0, false],
        [502, 'hourly', 39.08, false],
        [503, 'hourly', 64.50, false],
        [504, 'hourly', 67.84, false],
        [599, 'hourly', 100.06, false],
    ]],
    ['1212', 'boomop', 'Boom Operator', 'production_sound', 'IATSE', 61, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 30.06, false],
        [502, 'hourly', 31.66, false],
        [503, 'hourly', 47.49, false],
        [504, 'hourly', 50.02, false],
        [599, 'hourly', 77.70, false],
    ]],
    ['1214', 'soundtech', 'Sound Technician', 'production_sound', 'IATSE', 62, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 26.81, false],
        [502, 'hourly', 31.66, false],
        [503, 'hourly', 45.47, false],
        [504, 'hourly', 47.83, false],
        [599, 'hourly', 68.23, false],
    ]],

    // ── GRIP ──
    ['1310', 'headgrip', 'Head Grip', 'grip', 'IATSE', 70, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 33.26, false],
        [502, 'hourly', 34.87, false],
        [503, 'hourly', 43.75, false],
        [504, 'hourly', 46.00, false],
        [599, 'weekly', 3793.59, false],
    ]],
    ['1312', 'bestboygrip', 'Best Boy Grip', 'grip', 'IATSE', 71, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 30.06, false],
        [502, 'hourly', 31.66, false],
        [503, 'hourly', 43.75, false],
        [504, 'hourly', 46.04, false],
        [599, 'weekly', 3428.81, false],
    ]],
    ['1314', 'dollygrip', 'Dolly Grip', 'grip', 'IATSE', 72, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 30.06, false],
        [502, 'hourly', 31.66, false],
        [503, 'hourly', 40.97, false],
        [504, 'hourly', 43.15, false],
        [599, 'hourly', 59.19, false],
    ]],
    ['1316', 'companygrip', 'Company Grip 1', 'grip', 'IATSE', 73, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 26.81, false],
        [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false],
        [504, 'hourly', 39.79, false],
        [599, 'hourly', 54.78, false],
    ]],
    ['1318', 'companygrip', 'Company Grip 2', 'grip', 'IATSE', 74, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 26.81, false],
        [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false],
        [504, 'hourly', 39.79, false],
        [599, 'hourly', 54.78, false],
    ]],
    ['1320', 'companygrip', 'Company Grip 3', 'grip', 'IATSE', 75, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 26.81, false],
        [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false],
        [504, 'hourly', 39.79, false],
        [599, 'hourly', 54.78, false],
    ]],
    ['1322', 'companygrip', 'Company Grip 4', 'grip', 'IATSE', 76, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 26.81, false],
        [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false],
        [504, 'hourly', 39.79, false],
        [599, 'hourly', 54.78, false],
    ]],
    ['1324', 'companygrip', 'Company Grip 5', 'grip', 'IATSE', 77, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 26.81, false],
        [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false],
        [504, 'hourly', 39.79, false],
        [599, 'hourly', 54.78, false],
    ]],
    ['1326', 'companygrip', 'Company Grip 6', 'grip', 'IATSE', 78, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 27.07, false],
        [502, 'hourly', 28.69, false],
        [503, 'hourly', 38.14, false],
        [504, 'hourly', 40.18, false],
        [599, 'hourly', 54.05, false],
    ]],

    // ── ELECTRIC ──
    ['1410', 'chieflighting', 'Chief Lighting Tech / Gaffer', 'electric', 'IATSE', 80, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 33.26, false],
        [502, 'hourly', 34.87, false],
        [503, 'hourly', 43.75, false],
        [504, 'hourly', 46.04, false],
        [599, 'weekly', 3793.59, false],
    ]],
    ['1412', 'bestboyelectric', 'Best Boy Electric', 'electric', 'IATSE', 81, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 30.36, false],
        [502, 'hourly', 31.66, false],
        [503, 'hourly', 39.55, false],
        [504, 'hourly', 41.65, false],
        [599, 'weekly', 3428.81, false],
    ]],
    ['1414', 'chiefrigging', 'Chief Rigging Electrician', 'electric', 'IATSE', 82, [
        [500, 'min_wage', 0, false],
        [501, 'hourly', 33.26, false],
        [502, 'hourly', 34.87, false],
        [503, 'hourly', 43.75, false],
        [504, 'hourly', 46.04, false],
        [599, 'weekly', 3793.59, false],
    ]],
    ['1416', 'electric', 'Electric 1', 'electric', 'IATSE', 83, [
        [500, 'min_wage', 0, false], [501, 'hourly', 26.81, false], [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false], [504, 'hourly', 39.79, false], [599, 'hourly', 54.78, false],
    ]],
    ['1418', 'electric', 'Electric 2', 'electric', 'IATSE', 84, [
        [500, 'min_wage', 0, false], [501, 'hourly', 26.81, false], [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false], [504, 'hourly', 39.79, false], [599, 'hourly', 54.78, false],
    ]],
    ['1420', 'electric', 'Electric 3', 'electric', 'IATSE', 85, [
        [500, 'min_wage', 0, false], [501, 'hourly', 26.81, false], [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false], [504, 'hourly', 39.79, false], [599, 'hourly', 54.78, false],
    ]],
    ['1422', 'electric', 'Electric 4', 'electric', 'IATSE', 86, [
        [500, 'min_wage', 0, false], [501, 'hourly', 26.81, false], [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false], [504, 'hourly', 39.79, false], [599, 'hourly', 54.78, false],
    ]],
    ['1424', 'electric', 'Electric 5', 'electric', 'IATSE', 87, [
        [500, 'min_wage', 0, false], [501, 'hourly', 26.81, false], [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false], [504, 'hourly', 39.79, false], [599, 'hourly', 54.78, false],
    ]],
    ['1426', 'electric', 'Electric 6', 'electric', 'IATSE', 88, [
        [500, 'min_wage', 0, false], [501, 'hourly', 26.81, false], [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false], [504, 'hourly', 39.79, false], [599, 'hourly', 54.78, false],
    ]],

    // ── LOCATION ──
    ['1510', 'locationmanager', 'Location Manager', 'location', 'TEAMSTERS', 90, [
        [699, 'weekly', 4002.00, false],
    ]],
    ['1512', 'keyasstlocmgr', 'Key Asst Location Manager', 'location', 'TEAMSTERS', 91, [
        [699, 'weekly', 2700.00, false],
    ]],
    ['1514', 'asstlocmgr', 'Asst Location Manager', 'location', 'TEAMSTERS', 92, [
        [699, 'weekly', 2080.00, false],
    ]],
    ['1516', 'cookdriver', 'Cook / Driver', 'location', 'TEAMSTERS', 93, [
        [699, 'hourly', 35.80, false],
    ]],
    ['1518', 'crafty', 'Craft Services', 'location', 'IATSE', 94, [
        [500, 'min_wage', 0, false], [501, 'hourly', 30.06, false], [502, 'hourly', 31.66, false],
        [503, 'hourly', 39.55, false], [504, 'hourly', 41.65, false], [599, 'hourly', 52.35, false],
    ]],
    ['1520', 'medic', 'Set Medic', 'location', 'IATSE', 95, [
        [500, 'min_wage', 0, false], [501, 'hourly', 30.06, false], [502, 'hourly', 31.66, false],
        [503, 'hourly', 39.55, false], [504, 'hourly', 41.65, false], [599, 'hourly', 52.78, false],
    ]],

    // ── TRANSPORTATION ──
    ['1610', 'transpocoord', 'Transportation Coordinator', 'transportation', 'TEAMSTERS', 100, [
        [699, 'weekly', 4160.00, false],
    ]],
    ['1612', 'gangboss', 'Gang Boss', 'transportation', 'TEAMSTERS', 101, [
        [699, 'hourly', 52.42, false],
    ]],
    ['1614', 'truckdriver', 'Truck Driver', 'transportation', 'TEAMSTERS', 102, [
        [699, 'hourly', 46.45, false],
    ]],
    ['1616', 'vandriver', 'Van Driver', 'transportation', 'TEAMSTERS', 103, [
        [699, 'hourly', 58.80, false],
    ]],
    ['1618', 'craneop', 'Crane Operator', 'transportation', 'TEAMSTERS', 104, [
        [699, 'hourly', 57.61, false],
    ]],
    ['1620', 'cabdriver', 'Cab Driver', 'transportation', 'TEAMSTERS', 105, [
        [699, 'hourly', 50.09, false],
    ]],
    ['1622', 'maxivandriver', 'Maxi Van Driver', 'transportation', 'TEAMSTERS', 106, [
        [699, 'hourly', 52.44, false],
    ]],
    ['1624', 'autodriver', 'Auto Driver', 'transportation', 'TEAMSTERS', 107, [
        [699, 'hourly', 50.09, false],
    ]],
    ['1626', 'camcardriver', 'Camera Car Driver', 'transportation', 'TEAMSTERS', 108, [
        [699, 'hourly', 57.61, false],
    ]],
    ['1628', 'autoservice', 'Auto Service', 'transportation', 'TEAMSTERS', 109, [
        [699, 'hourly', 28.38, false],
    ]],

    // ── ART ──
    ['1710', 'productiondesigner', 'Production Designer', 'art', 'IATSE', 110, [
        [500, 'min_wage', 0, false], [501, 'min_wage', 0, false], [502, 'weekly', 1000.00, false],
        [503, 'weekly', 2000.00, false], [504, 'weekly', 3000.00, false], [599, 'weekly', 4988.70, false],
    ]],
    ['1712', 'artdirector', 'Art Director', 'art', 'IATSE', 111, [
        [500, 'min_wage', 0, false], [501, 'min_wage', 0, false], [502, 'min_wage', 0, false],
        [503, 'weekly', 3438.68, false], [504, 'weekly', 3684.34, false], [599, 'weekly', 4552.88, false],
    ]],
    ['1714', 'asstartdir', 'Asst Art Director', 'art', 'IATSE', 112, [
        [500, 'min_wage', 0, false], [501, 'min_wage', 0, false], [502, 'min_wage', 0, false],
        [503, 'min_wage', 0, false], [504, 'min_wage', 0, false], [599, 'weekly', 3766.15, false],
    ]],
    ['1716', 'setdesigner', 'Set Designer', 'art', 'IATSE', 113, [
        [500, 'min_wage', 0, false], [501, 'hourly', 33.26, false], [502, 'hourly', 34.87, false],
        [503, 'hourly', 46.51, false], [504, 'hourly', 48.94, false], [599, 'weekly', 2535.60, false],
    ]],
    ['1718', 'artdeptcoord', 'Art Dept Coordinator', 'art', 'IATSE', 114, [
        [500, 'min_wage', 0, false], [501, 'hourly', 30.06, false], [502, 'hourly', 31.66, false],
        [503, 'hourly', 39.55, false], [504, 'hourly', 41.65, false], [599, 'min_wage', 0, false],
    ]],

    // ── CONSTRUCTION ──
    ['1810', 'constcoord', 'Construction Coordinator', 'construction', 'IATSE', 120, [
        [500, 'min_wage', 0, false], [501, 'min_wage', 0, false], [502, 'weekly', 1000.00, false],
        [503, 'weekly', 1500.00, false], [504, 'weekly', 2000.00, false], [599, 'weekly', 4048.34, false],
    ]],
    ['1812', 'propmakerforeman', 'Prop Maker Foreman', 'construction', 'IATSE', 121, [
        [500, 'min_wage', 0, false], [501, 'hourly', 33.26, false], [502, 'hourly', 34.87, false],
        [503, 'hourly', 41.42, false], [504, 'hourly', 46.71, false], [599, 'weekly', 3414.76, false],
    ]],
    ['1814', 'propmakergang', 'Prop Maker Gang', 'construction', 'IATSE', 122, [
        [500, 'min_wage', 0, false], [501, 'hourly', 30.06, false], [502, 'hourly', 31.66, false],
        [503, 'hourly', 39.55, false], [504, 'hourly', 41.65, false], [599, 'hourly', 59.19, false],
    ]],
    ['1816', 'propmakerjourney', 'Prop Maker Journeyman', 'construction', 'IATSE', 123, [
        [500, 'min_wage', 0, false], [501, 'hourly', 26.81, false], [502, 'hourly', 28.42, false],
        [503, 'hourly', 38.64, false], [504, 'hourly', 40.66, false], [599, 'hourly', 55.95, false],
    ]],
    ['1818', 'leadscenic', 'Lead Scenic', 'construction', 'IATSE', 124, [
        [500, 'min_wage', 0, false], [501, 'hourly', 30.00, false], [502, 'hourly', 40.00, false],
        [503, 'hourly', 50.00, false], [504, 'hourly', 60.00, false], [599, 'hourly', 81.84, false],
    ]],
    ['1820', 'forepainter', 'Fore Painter', 'construction', 'IATSE', 125, [
        [500, 'min_wage', 0, false], [501, 'hourly', 33.26, false], [502, 'hourly', 34.87, false],
        [503, 'hourly', 48.09, false], [504, 'hourly', 50.63, false], [599, 'weekly', 3414.76, false],
    ]],
    ['1822', 'painter', 'Painter', 'construction', 'IATSE', 126, [
        [500, 'min_wage', 0, false], [501, 'hourly', 26.81, false], [502, 'hourly', 28.42, false],
        [503, 'hourly', 41.85, false], [504, 'hourly', 44.07, false], [599, 'hourly', 55.95, false],
    ]],
    ['1824', 'laborer', 'Laborer', 'construction', 'IATSE', 127, [
        [500, 'min_wage', 0, false], [501, 'hourly', 20.00, false], [502, 'hourly', 25.00, false],
        [503, 'hourly', 30.00, false], [504, 'hourly', 35.00, false], [599, 'hourly', 43.66, false],
    ]],

    // ── SET DRESSING ──
    ['1910', 'setdecorator', 'Set Decorator', 'set_dressing', 'IATSE', 130, [
        [500, 'min_wage', 0, false], [501, 'hourly', 35.00, false], [502, 'hourly', 40.00, false],
        [503, 'hourly', 50.00, false], [504, 'hourly', 55.00, false], [599, 'weekly', 4156.58, false],
    ]],
    ['1912', 'swinggang', 'Swing Gang', 'set_dressing', 'IATSE', 131, [
        [500, 'min_wage', 0, false], [501, 'hourly', 26.81, false], [502, 'hourly', 28.42, false],
        [503, 'hourly', 37.77, false], [504, 'hourly', 39.79, false], [599, 'hourly', 52.36, false],
    ]],

    // ── PROPERTY ──
    ['2010', 'propmaster', 'Property Master', 'property', 'IATSE', 140, [
        [500, 'min_wage', 0, false], [501, 'hourly', 33.26, false], [502, 'hourly', 34.87, false],
        [503, 'hourly', 43.75, false], [504, 'hourly', 46.04, false], [599, 'weekly', 4353.30, false],
    ]],
    ['2012', 'asstpropmaster', 'Asst Property Master', 'property', 'IATSE', 141, [
        [500, 'min_wage', 0, false], [501, 'hourly', 30.06, false], [502, 'hourly', 31.66, false],
        [503, 'hourly', 39.55, false], [504, 'hourly', 41.65, false], [599, 'weekly', 3847.70, false],
    ]],
    ['2014', 'propperson', 'Prop Person', 'property', 'IATSE', 142, [
        [500, 'min_wage', 0, false], [501, 'hourly', 27.07, false], [502, 'hourly', 35.00, false],
        [503, 'hourly', 40.00, false], [504, 'hourly', 45.00, false], [599, 'hourly', 52.36, false],
    ]],
    ['2016', 'greensperson', 'Greens Person', 'property', 'IATSE', 143, [
        [500, 'min_wage', 0, false], [501, 'hourly', 30.36, false], [502, 'hourly', 31.66, false],
        [503, 'hourly', 39.55, false], [504, 'hourly', 41.65, false], [599, 'hourly', 50.35, false],
    ]],

    // ── WARDROBE ──
    ['2110', 'costumedesigner', 'Costume Designer', 'wardrobe', 'IATSE', 150, [
        [500, 'min_wage', 0, false], [501, 'hourly', 32.00, false], [502, 'hourly', 40.00, false],
        [503, 'hourly', 50.00, false], [504, 'hourly', 60.00, false], [599, 'weekly', 4988.70, false],
    ]],
    ['2112', 'assistantcostumedesigner', 'Asst Costume Designer', 'wardrobe', 'IATSE', 151, [
        [500, 'min_wage', 0, false], [501, 'hourly', 30.06, false], [502, 'hourly', 31.66, false],
        [503, 'hourly', 39.55, false], [504, 'hourly', 41.65, false], [599, 'weekly', 3139.61, false],
    ]],
    ['2114', 'keycostumer', 'Key Costumer', 'wardrobe', 'IATSE', 152, [
        [500, 'min_wage', 0, false], [501, 'hourly', 33.26, false], [502, 'hourly', 34.87, false],
        [503, 'hourly', 43.75, false], [504, 'hourly', 46.04, false], [599, 'hourly', 59.17, false],
    ]],
    ['2116', 'costumer', 'Costumer', 'wardrobe', 'IATSE', 153, [
        [500, 'min_wage', 0, false], [501, 'hourly', 26.81, false], [502, 'hourly', 28.42, false],
        [503, 'hourly', 33.77, false], [504, 'hourly', 39.79, false], [599, 'hourly', 51.75, false],
    ]],

    // ── HAIR & MAKEUP ──
    ['2210', 'headmakeupartist', 'Head Makeup Artist', 'hair_makeup', 'IATSE', 160, [
        [500, 'min_wage', 0, false], [501, 'hourly', 33.26, false], [502, 'hourly', 34.87, false],
        [503, 'hourly', 50.28, false], [504, 'hourly', 52.96, false], [599, 'weekly', 4347.50, false],
    ]],
    ['2212', 'makeupartist', 'Makeup Artist', 'hair_makeup', 'IATSE', 161, [
        [500, 'min_wage', 0, false], [501, 'hourly', 30.06, false], [502, 'hourly', 31.66, false],
        [503, 'hourly', 42.78, false], [504, 'hourly', 45.01, false], [599, 'weekly', 4347.50, false],
    ]],
    ['2214', 'headhairstylist', 'Head Hairstylist', 'hair_makeup', 'IATSE', 162, [
        [500, 'min_wage', 0, false], [501, 'hourly', 33.26, false], [502, 'hourly', 34.87, false],
        [503, 'hourly', 50.28, false], [504, 'hourly', 52.96, false], [599, 'weekly', 4347.50, false],
    ]],
    ['2216', 'hairstylist', 'Hairstylist', 'hair_makeup', 'IATSE', 163, [
        [500, 'min_wage', 0, false], [501, 'hourly', 30.06, false], [502, 'hourly', 31.66, false],
        [503, 'hourly', 42.78, false], [504, 'hourly', 45.01, false], [599, 'weekly', 4347.50, false],
    ]],

    // ── POST PRODUCTION ──
    ['2920', 'editor', 'Editor', 'post_production', 'IATSE', 170, [
        [500, 'min_wage', 0, false], [501, 'weekly', 1750.00, false], [502, 'weekly', 2500.00, false],
        [503, 'weekly', 3000.00, false], [504, 'weekly', 4168.09, false], [599, 'weekly', 4467.11, false],
    ]],
    ['2922', 'assistanteditor', 'Assistant Editor', 'post_production', 'IATSE', 171, [
        [500, 'min_wage', 0, false], [501, 'hourly', 33.26, false], [502, 'hourly', 39.07, false],
        [503, 'weekly', 2421.45, false], [504, 'weekly', 2549.55, false], [599, 'weekly', 2631.98, false],
    ]],

    // ── POST SOUND ──
    ['3110', 'suprvsoundeditor', 'Supervising Sound Editor', 'post_sound', 'IATSE', 180, [
        [500, 'min_wage', 0, false], [501, 'weekly', 1500.00, false], [502, 'weekly', 2000.00, false],
        [503, 'weekly', 3003.05, false], [504, 'weekly', 3083.33, false], [599, 'weekly', 3479.36, false],
    ]],
    ['3120', 'soundmixer', 'Sound Mixer', 'post_sound', 'IATSE', 181, [
        [500, 'min_wage', 0, false], [501, 'weekly', 1200.00, false], [502, 'weekly', 1500.00, false],
        [503, 'hourly', 70.46, false], [504, 'hourly', 74.13, false], [599, 'weekly', 4124.68, false],
    ]],
    ['3130', 'soundeffectseditor', 'Sound Effects Editor', 'post_sound', 'IATSE', 182, [
        [500, 'min_wage', 0, false], [501, 'weekly', 1000.00, false], [502, 'weekly', 2000.00, false],
        [503, 'weekly', 3003.05, false], [504, 'weekly', 3083.33, false], [599, 'weekly', 3273.70, false],
    ]],
    ['3140', 'foleyartist', 'Foley Artist', 'post_sound', 'IATSE', 183, [
        [500, 'min_wage', 0, false], [501, 'weekly', 1000.00, false], [502, 'weekly', 2000.00, false],
        [503, 'weekly', 3003.05, false], [504, 'weekly', 3083.33, false], [599, 'weekly', 3273.70, false],
    ]],
    ['3150', 'musicrerecording', 'Music / Re-Recording', 'post_sound', 'IATSE', 184, [
        [500, 'min_wage', 0, false], [501, 'weekly', 1000.00, false], [502, 'weekly', 1500.00, false],
        [503, 'hourly', 70.46, false], [504, 'hourly', 74.13, false], [599, 'weekly', 4124.68, false],
    ]],
    ['3160', 'engineer', 'Engineer', 'post_sound', 'IATSE', 185, [
        [500, 'min_wage', 0, false], [501, 'hourly', 45.00, false], [502, 'hourly', 50.00, false],
        [503, 'hourly', 55.00, false], [504, 'hourly', 60.00, false], [599, 'weekly', 4124.68, false],
    ]],
];
