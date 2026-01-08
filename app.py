from flask import Flask, request
import pymysql
import random
import string
from werkzeug.security import generate_password_hash, check_password_hash

app = Flask(__name__)

# ---------------- DATABASE CONNECTION ----------------
def get_db_connection():
    return pymysql.connect(
        host="localhost",
        user="root",
        password="",
        database="pro_builder",
        port=3306
    )

# ---------------- HOME ----------------
@app.route("/")
def home():
    return "Backend is running"

# ---------------- SIGNUP ----------------
@app.route("/signup", methods=["POST"])
def signup():
    name = request.form.get("name")
    email = request.form.get("email")
    password = request.form.get("password")
    role = "contractor"

    if not name or not email or not password:
        return "missing_parameters"

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("SELECT id FROM users WHERE email=%s", (email,))
    if cur.fetchone():
        cur.close()
        conn.close()
        return "exists"

    hashed = generate_password_hash(password)

    cur.execute(
        "INSERT INTO users (role, name, email, password) VALUES (%s,%s,%s,%s)",
        (role, name, email, hashed)
    )
    conn.commit()

    cur.close()
    conn.close()
    return "success"

# ---------------- LOGIN ----------------
@app.route("/login", methods=["GET", "POST"])
def login():
    email = request.values.get("email")
    password = request.values.get("password")
    role = "contractor"

    if not email or not password:
        return "missing_parameters"

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute(
        "SELECT password FROM users WHERE email=%s AND role=%s",
        (email, role)
    )
    row = cur.fetchone()

    cur.close()
    conn.close()

    if not row:
        return "invalid"

    return "success" if check_password_hash(row[0], password) else "invalid"

# ---------------- ADD CLIENT ----------------
@app.route("/add-client", methods=["POST"])
def add_client():
    name = request.form.get("name")
    email = request.form.get("email")
    contractor_id = request.form.get("contractor_id")

    if not name or not email:
        return "missing_parameters"

    temp_password = ''.join(
        random.choices(string.ascii_letters + string.digits, k=8)
    )
    hashed_password = generate_password_hash(temp_password)

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("SELECT id FROM users WHERE email=%s", (email,))
    if cur.fetchone():
        cur.close()
        conn.close()
        return "exists"

    cur.execute("""
        INSERT INTO users (role, name, email, password)
        VALUES ('client', %s, %s, %s)
    """, (name, email, hashed_password))

    conn.commit()
    cur.close()
    conn.close()

    print("===== CLIENT LOGIN DETAILS =====")
    print("Email:", email)
    print("Password:", temp_password)
    print("================================")

    return "success"

# ---------------- INVITE CLIENT ----------------
@app.route("/invite-client", methods=["POST"])
def invite_client():

    name = request.form.get("name")
    email = request.form.get("email")
    phone = request.form.get("phone")
    contractor_id = request.form.get("contractor_id")

    if not name or not email or not phone or not contractor_id:
        return "missing_parameters"

    try:
        conn = get_db_connection()
        cur = conn.cursor()

        # prevent duplicate invite
        cur.execute("""
            SELECT id FROM client_invites
            WHERE client_email=%s AND contractor_id=%s
        """, (email, contractor_id))

        if cur.fetchone():
            cur.close()
            conn.close()
            return "exists"

        invite_token = ''.join(
            random.choices(string.ascii_letters + string.digits, k=32)
        )

        cur.execute("""
            INSERT INTO client_invites
            (contractor_id, client_name, client_email, client_phone, invite_token)
            VALUES (%s, %s, %s, %s, %s)
        """, (
            contractor_id,
            name,
            email,
            phone,
            invite_token
        ))

        conn.commit()
        cur.close()
        conn.close()

        print("INVITE CREATED:", email, invite_token)
        return "success"

    except Exception as e:
        print("INVITE ERROR:", e)
        return "server_error"

# ---------------- ADD PROJECT ----------------
@app.route("/add-project", methods=["POST"])
def add_project():
    project_name = request.form.get("project_name")
    client_name = request.form.get("client_name")
    start_date = request.form.get("start_date")
    end_date = request.form.get("end_date")
    contractor_id = request.form.get("contractor_id")

    if not project_name or not client_name or not contractor_id:
        return "missing_parameters"

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("""
        INSERT INTO projects
        (contractor_id, project_name, client_name, start_date, end_date, status)
        VALUES (%s, %s, %s, %s, %s, %s)
    """, (
        contractor_id,
        project_name,
        client_name,
        start_date,
        end_date,
        "Active"
    ))

    conn.commit()
    cur.close()
    conn.close()

    return "success"

# ---------------- GET PROJECTS ----------------
@app.route("/projects", methods=["GET"])
def get_projects():
    contractor_id = request.args.get("contractor_id")

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("""
        SELECT id, project_name, location, client_name, client_phone,
               start_date, end_date, status
        FROM projects
        WHERE contractor_id=%s
        ORDER BY id DESC
    """, (contractor_id,))

    rows = cur.fetchall()
    cur.close()
    conn.close()

    projects = []
    for r in rows:
        projects.append({
            "id": r[0],
            "project_name": r[1],
            "location": r[2],
            "client_name": r[3],
            "client_phone": r[4],
            "start_date": str(r[5]),
            "end_date": str(r[6]),
            "status": r[7]
        })

    return {"projects": projects}

# ================== STEP 2 STARTS HERE ==================

# ---------------- UPDATE PROJECT ----------------
@app.route("/update-project", methods=["POST"])
def update_project():
    project_id = request.form.get("project_id")
    project_name = request.form.get("project_name")
    location = request.form.get("location")
    client_name = request.form.get("client_name")
    client_phone = request.form.get("client_phone")
    start_date = request.form.get("start_date")
    end_date = request.form.get("end_date")
    status = request.form.get("status")

    if not project_id:
        return "missing_parameters"

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("""
        UPDATE projects SET
            project_name=%s,
            location=%s,
            client_name=%s,
            client_phone=%s,
            start_date=%s,
            end_date=%s,
            status=%s
        WHERE id=%s
    """, (
        project_name,
        location,
        client_name,
        client_phone,
        start_date,
        end_date,
        status,
        project_id
    ))

    conn.commit()
    cur.close()
    conn.close()

    return "success"

# ---------------- DELETE PROJECT ----------------
@app.route("/delete-project", methods=["POST"])
def delete_project():
    project_id = request.form.get("project_id")

    if not project_id:
        return "missing_parameters"

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("DELETE FROM projects WHERE id=%s", (project_id,))
    conn.commit()

    cur.close()
    conn.close()

    return "success"

# ---------------- GET CLIENTS ----------------
@app.route("/clients", methods=["GET"])
def get_clients():
    contractor_id = request.args.get("contractor_id")

    if not contractor_id:
        return {"status": "missing_parameters"}, 400

    try:
        conn = get_db_connection()
        cur = conn.cursor(pymysql.cursors.DictCursor)

        cur.execute("""
            SELECT 
                id,
                client_name,
                client_email,
                client_phone,
                is_used,
                created_at
            FROM client_invites
            WHERE contractor_id = %s
            ORDER BY id DESC
        """, (contractor_id,))

        rows = cur.fetchall()
        cur.close()
        conn.close()

        return {
            "status": "success",
            "clients": rows
        }

    except Exception as e:
        print("GET CLIENTS ERROR:", e)
        return {"status": "server_error"}, 500

# ---------------- ADD QUOTATION ----------------
@app.route("/add-quotation", methods=["POST"])
def add_quotation():
    contractor_id = request.form.get("contractor_id")
    client_id = request.form.get("client_id")
    project_id = request.form.get("project_id")
    title = request.form.get("title")
    description = request.form.get("description")
    amount = request.form.get("amount")

    if not contractor_id or not client_id or not project_id or not title or not amount:
        return {"status": "error", "message": "missing_parameters"}

    try:
        conn = get_db_connection()
        cur = conn.cursor()

        cur.execute("""
            INSERT INTO quotations
            (contractor_id, client_id, project_id, title, description, amount)
            VALUES (%s, %s, %s, %s, %s, %s)
        """, (
            contractor_id,
            client_id,
            project_id,
            title,
            description,
            amount
        ))

        conn.commit()
        cur.close()
        conn.close()

        return {"status": "success"}

    except Exception as e:
        print("ADD QUOTATION ERROR:", e)
        return {"status": "error", "message": "server_error"}

# ---------------- GET QUOTATIONS ----------------
@app.route("/quotations", methods=["GET"])
def get_quotations():
    contractor_id = request.args.get("contractor_id")

    conn = get_db_connection()
    cur = conn.cursor(pymysql.cursors.DictCursor)

    cur.execute("""
        SELECT q.id, q.title, q.amount, q.created_at,
               c.client_name,
               p.project_name
        FROM quotations q
        JOIN client_invites c ON q.client_id = c.id
        JOIN projects p ON q.project_id = p.id
        WHERE q.contractor_id = %s
        ORDER BY q.id DESC
    """, (contractor_id,))

    quotations = cur.fetchall()
    cur.close()
    conn.close()

    return {
        "status": "success",
        "quotations": quotations
    }



# ---------------- QUOTATION DETAIL ----------------
@app.route("/quotation/<int:quotation_id>", methods=["GET"])
def quotation_detail(quotation_id):
    try:
        conn = get_db_connection()
        cur = conn.cursor(pymysql.cursors.DictCursor)

        cur.execute("""
            SELECT 
                q.*,
                u.name AS client_name,
                u.email AS client_email,
                p.project_name
            FROM quotations q
            JOIN users u ON q.client_id = u.id
            JOIN projects p ON q.project_id = p.id
            WHERE q.id = %s
        """, (quotation_id,))

        row = cur.fetchone()
        cur.close()
        conn.close()

        if not row:
            return {"status": "error", "message": "not_found"}

        return {
            "status": "success",
            "quotation": row
        }

    except Exception as e:
        print("QUOTATION DETAIL ERROR:", e)
        return {"status": "error", "message": "server_error"}

# ---------------- RUN SERVER ----------------
if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
