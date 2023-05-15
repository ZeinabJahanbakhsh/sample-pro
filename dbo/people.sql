create table people
(
    id            smallint identity
        constraint PK_people
            primary key,
    first_name    nvarchar(50) not null,
    last_name     nvarchar(50) not null,
    national_code char(10)     not null,
    mobile        nvarchar(15),
    email         char(50)     not null,
    birthdate     date         not null,
    department_id smallint
        constraint FK_people_department
            references departments,
    grade_id      smallint
        constraint FK_people_grade
            references grades,
    employment_no int,
    created_at    datetime     not null,
    updated_at    datetime     not null
)
go

