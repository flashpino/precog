import mysql from 'mysql2/promise';

async function main() {
    const connection = await mysql.createConnection({
        host: '212.1.210.112',
        user: 'asfindbr_esp32',
        password: 'nminfo*4990',
        database: 'asfindbr_precog'
    });

    try {
        const [[timeInfo]] = await connection.execute('select now() as mysql_now, utc_timestamp() as mysql_utc_now');
        console.log('MySQL Server Time Info:', timeInfo);

        // console.log('--- CLIENTS ---');
        // const [clients] = await connection.execute('select id, name, company from clients');
        // console.table(clients);

        // console.log('--- SENSORS ---');
        // const [sensors] = await connection.execute('select id, device_id, label, client_id, temp_min, temp_max, alert_state_temp, last_status, last_seen from sensors');
        // console.table(sensors);

        console.log('--- CONTACTS & PREFERENCES ---');
        const [contacts] = await connection.execute('select c.id, c.name, c.phone, c.is_active, c.is_admin, c.client_id, p.alert_type, p.days_of_week, p.time_start, p.time_end, p.min_interval from contacts c left join contact_alert_preferences p on c.id = p.contact_id');
        console.table(contacts);

        console.log('--- LATEST 20 ALERTS ---');
        const [alerts] = await connection.execute('select id, sensor_id, type, message, value, threshold, webhook_sent, created_at from alerts order by id desc limit 20');
        console.table(alerts);
        
        console.log('--- ALL ALERT TYPES COUNT ---');
        const [alertCounts] = await connection.execute('select type, count(*) as count from alerts group by type');
        console.table(alertCounts);

        console.log('--- LATEST 20 SENT ALERTS LOGS ---');
        const [logs] = await connection.execute('select * from sent_alerts_logs order by id desc limit 20');
        console.table(logs);
        
        console.log('--- SENT ALERTS LOGS COLUMNS ---');
        const [columns] = await connection.execute('describe sent_alerts_logs');
        console.table(columns);

        console.log('--- LATEST 20 EVENTS ---');
        const [events] = await connection.execute('select * from events order by id desc limit 20');
        console.table(events);

    } catch (err) {
        console.error(err);
    } finally {
        await connection.end();
    }
}

main();
